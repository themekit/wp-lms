<?php namespace Mosaicpro\WP\Plugins\LMS;

use Mosaicpro\HtmlGenerators\Core\IoC;
use Mosaicpro\WpCore\CRUD;
use Mosaicpro\WpCore\Date;
use Mosaicpro\WpCore\FormBuilder;
use Mosaicpro\WpCore\MetaBox;
use Mosaicpro\WpCore\PluginGeneric;
use Mosaicpro\WpCore\PostList;
use Mosaicpro\WpCore\PostType;
use Mosaicpro\WpCore\Taxonomy;
use Mosaicpro\WpCore\ThickBox;
use WP_Query;

/**
 * Class Courses
 * @package Mosaicpro\WP\Plugins\LMS
 */
class Courses extends PluginGeneric
{
    /**
     * Holds a Courses instance
     * @var
     */
    protected static $instance;

    /**
     * Holds the Quizzes dependency
     * @var bool|mixed
     */
    protected $quizzes;

    /**
     * Create a new Courses instance
     */
    public function __construct()
    {
        parent::__construct();
        $this->quizzes = is_plugin_active('mp-quiz/index.php') ? IoC::getContainer('quizzes') : false;
    }

    /**
     * Get a Courses Singleton instance
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(self::$instance))
        {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * Provide access to the Quizzes dependency outside of this class
     * @return bool|mixed
     */
    public function getQuizzes()
    {
        return $this->quizzes;
    }

    /**
     * Initialize the Courses plugin
     */
    public static function init()
    {
        $instance = self::getInstance();

        // Initialize Courses Templates
        $instance->initTemplates();

        // Initialize Courses Sidebars
        $instance->initSidebars();

        // Initialize Courses Widgets
        $instance->initWidgets();

        // Initialize Courses Shortcodes
        $instance->initShortcodes();

        // Add default sidebar widgets
        // $instance->initSidebarWidgets();

        // Initialize Admin
        $instance->initAdmin();

        // Initialize Shared Resources
        $instance->initShared();
    }

    /**
     * Activate Courses plugin
     */
    public static function activate()
    {
        $instance = self::getInstance();
        $instance->post_types();
        flush_rewrite_rules();
    }

    /**
     * Initialize Courses Templates
     */
    private function initTemplates()
    {
        // Set Custom page templates
        $this->plugin->setPageTemplates([
            'single-' . $this->getPrefix('course') . '-full_width.php' => $this->__('Single Course Full Width'),
            'single-' . $this->getPrefix('course') . '-sidebar_left.php' => $this->__('Single Course Left Sidebar'),
            'single-' . $this->getPrefix('course') . '-sidebar_right.php' => $this->__('Single Course Right Sidebar')
        ]);

        // Display Custom page templates in WP Admin Post screen
        $this->plugin->initPostTemplates($this->getPrefix('course'));
    }

    /**
     * Initialize Courses Sidebars
     */
    private function initSidebars()
    {
        add_action('widgets_init', function()
        {
            register_sidebar([
                'name'          => $this->__( 'Single Course Sidebar' ),
                'id'            => 'single-course-sidebar',
                'description'   => $this->__('Sidebar or Widget Area used on the single Course page'),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div><hr/>',
                'before_title'  => '<h4 class="widgettitle">',
                'after_title'   => '</h4>',
            ]);

            register_sidebar([
                'name'          => $this->__( 'Single Course Content' ),
                'id'            => 'single-course-content',
                'description'   => $this->__('Widget Area used on the single Course page for displaying widgets within the context of course content.'),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div><hr/>',
                'before_title'  => '<h4 class="widgettitle">',
                'after_title'   => '</h4>',
            ]);
        });
    }

    /**
     * Initialize Courses Widgets
     */
    private function initWidgets()
    {
        add_action('widgets_init', function()
        {
            $widgets = [
                'Curriculum',
                'Course_Information',
                'Instructor'
            ];

            foreach ($widgets as $widget)
            {
                require_once realpath(__DIR__) . '/Widgets/' . $widget . '.php';
                register_widget(__NAMESPACE__ . '\\' . $widget . '_Widget');
            }
        });
    }

    /**
     * Initialize Courses Shortcodes
     */
    private function initShortcodes()
    {
        add_action('init', function()
        {
            $shortcodes = [
                'Course_Information',
                'Curriculum',
                'Instructor'
            ];

            foreach ($shortcodes as $sc)
            {
                require_once realpath(__DIR__) . '/Shortcodes/' . $sc . '.php';
                forward_static_call([__NAMESPACE__ . '\\' . $sc . '_Shortcode', 'init']);
            }
        });
    }

    /**
     * Init default sidebar widgets
     * @todo: Should be runned once, maybe at plugin activation or directly from the theme;
     */
    private function initSidebarWidgets()
    {
        add_action('widgets_init', function()
        {
            $sidebars = [
                'single-course-sidebar' => [
                    'Course_Information_Widget',
                    'Download_Attachments_Widget',
                    'Instructor_Widget'
                ],
                'single-lesson-sidebar' => [
                    'Course_Information_Widget',
                    'Download_Attachments_Widget',
                    'Instructor_Widget'
                ],
                'single-course-content' => [
                    'Curriculum_Widget'
                ]
            ];

            $active_widgets = get_option( 'sidebars_widgets' );
            foreach($sidebars as $sidebar => $widgets)
            {
                if ( ! empty ( $active_widgets[ $sidebar ] )) continue;
                $counter = 1;

                foreach ($widgets as $widget)
                {
                    $active_widgets[$sidebar][] = strtolower($widget) . '-' . $counter;
                    // update widget options
                    // $widget_content[$counter] = ['title' => 'Custom title'];
                    // update_option('widget_' . strtolower($widget), $widget_content);
                }
            }

            update_option( 'sidebars_widgets', $active_widgets );
        });
    }

    /**
     * Initialize the Admin Resources
     * @return bool
     */
    private function initAdmin()
    {
        if (!is_admin()) return false;
        $this->metaboxes();
        $this->crud();
        $this->admin_post_list();
    }

    /**
     * Initialize the Shared Resources
     */
    private function initShared()
    {
        $this->post_types();
        $this->taxonomies();
    }

    /**
     * Create the Courses post type
     */
    private function post_types()
    {
        PostType::make('course', $this->prefix)
            ->setOptions(['show_in_menu' => $this->prefix, 'supports' => ['title', 'thumbnail']])
            ->register();
    }

    /**
     * Create the Taxonomies
     */
    private function taxonomies()
    {
        Taxonomy::make('topic')
            ->setPostType([$this->getPrefix('lesson'), $this->getPrefix('course')])
            ->setOption('args', ['show_admin_column' => true])
            ->register();
    }

    /**
     * Create the Meta Boxes
     */
    private function metaboxes()
    {
        // Course Description Editor
        add_action('edit_form_after_title', function($post)
        {
            if ($post->post_type !== $this->getPrefix('course')) return;
            echo '<h2>' . $this->__('Course Description') . '</h2>';
            wp_editor($post->post_content, 'editpost', ['textarea_name' => 'post_content', 'textarea_rows' => 5]);
            echo "<br/>";
        });

        // Course -> Curriculum Meta Box
        MetaBox::make($this->prefix, 'curriculum', $this->__('Course Curriculum'))
            ->setPostType($this->getPrefix('course'))
            ->setDisplay($this->getCurriculumMetaboxDisplay())
            ->register();
    }

    /**
     * @return array
     */
    private function getCurriculumMetaboxDisplay()
    {
        $curriculum_related = [$this->getPrefix('lesson')];
        if ($this->quizzes)
            $curriculum_related[] = $this->quizzes->getPrefix('quiz');

        $display = [
            CRUD::getListContainer($curriculum_related),
            ThickBox::register_iframe( 'thickbox_lessons', $this->__('Add Lessons'), 'admin-ajax.php',
                ['action' => 'list_' . $this->getPrefix('course') . '_' . $this->getPrefix('lesson')] )->render()
        ];
        if ($this->quizzes)
            $display[] = ThickBox::register_iframe( 'thickbox_quizez', $this->__('Add Quizzes'), 'admin-ajax.php',
                ['action' => 'list_' . $this->getPrefix('course') . '_' . $this->quizzes->getPrefix('quiz')] )->render();

        return $display;
    }

    /**
     * Create CRUD Relationships
     */
    private function crud()
    {
        $curriculum_related = [$this->getPrefix('lesson')];
        if ($this->quizzes)
            $curriculum_related[] = $this->quizzes->getPrefix('quiz');

        // Courses -> Lessons & Quiz Mixed CRUD Relationship
        $curriculum_crud = CRUD::make($this->prefix, $this->getPrefix('course'), $curriculum_related)
            ->setListFields($this->getPrefix('lesson'), [
                'ID',
                'post_thumbnail',
                // Custom Title & Duration Column
                function($post)
                {
                    return [
                        'field' => $this->__('Title'),
                        'value' => $post->post_title .
                            '<p><small class="label label-default">' .
                            Date::time_format(implode(":", $post->duration)) . '</small></p>'
                    ];
                }
            ])
            ->setForm($this->getPrefix('lesson'), function($post)
            {
                FormBuilder::select_hhmmss('duration', $this->__('Duration'), $post->duration);
            });

        if ($this->quizzes)
        {
            $curriculum_crud->setListFields($this->getPrefix('quiz'), [
                'ID',
                'post_title',
                'count_' . $this->getPrefix('quiz_unit')
            ])
            ->setForm($this->getPrefix('quiz'), function($post)
            {
                FormBuilder::input('score_max', $this->__('Max. Score'), $post->score_max);
            });
        }

        $curriculum_crud->register();

        CRUD::setPostTypeLabel($this->getPrefix('lesson'), $this->__('Lesson'));
    }

    /**
     * Customize the WP Admin post list
     */
    private function admin_post_list()
    {
        $columns = [
            ['thumbnail', $this->__('Thumbnail'), 1],
            ['lessons', $this->__('Lessons'), 3],
            ['duration', $this->__('Duration'), 4],
            ['author', $this->__('Instructor'), 4]
        ];

        if ($this->quizzes)
            $columns[] = ['quizzes', $this->__('Quizzes'), 4];

        // Add Courses Listing Custom Columns
        PostList::add_columns($this->getPrefix('course'), $columns);

        // Display Courses Listing Custom Columns
        PostList::bind_column($this->getPrefix('course'), function($column, $post_id)
        {
            if ($column == 'duration')
                echo self::get_duration($post_id);

            if ($column == 'lessons')
            {
                $lessons = get_post_meta($post_id, $this->getPrefix('lesson'));
                echo count($lessons);
            }
            if ($column == 'quizzes')
            {
                $quizez = get_post_meta($post_id, $this->quizzes->getPrefix('quiz'));
                echo count($quizez);
            }
        });
    }

    /**
     * Get the duration of all Lessons within a Course
     * @param $course_id
     * @return string
     */
    public static function get_duration($course_id)
    {
        $lessons = get_post_meta($course_id, IoC::getContainer('plugin')->getPrefix('lesson'));
        $duration = [];
        foreach($lessons as $lesson_id)
            $duration[] = Date::time_to_seconds(implode(":", get_post_meta($lesson_id, 'duration', true)));

        return Date::seconds_to_time(array_sum($duration));
    }

    /**
     * Get curriculum by $course_id
     * @param $course_id
     * @return WP_Query
     */
    public function get_curriculum($course_id)
    {
        $related_types = [$this->getPrefix('lesson')];
        if ($this->quizzes) $related_types[] = $this->quizzes->getPrefix('quiz');

        $list = [];
        foreach ($related_types as $related_type)
        {
            $values = get_post_custom_values($related_type, $course_id);
            if (!is_null($values)) $list = array_merge($list, $values);
        }

        if (empty($list)) return false;

        $related_posts_args = [
            'post_type' => $related_types,
            'numberposts' => -1,
            'post__in' => $list
        ];

        $related_posts = new WP_Query($related_posts_args);

        $order_key = '_order_' . $this->getPrefix('lesson');
        if ($this->quizzes) $order_key .=  '_' . $this->quizzes->getPrefix('quiz');

        $order = get_post_meta($course_id, $order_key, true);
        $posts = CRUD::order_sortables($related_posts->posts, $order);
        $related_posts->posts = $posts;

        wp_reset_postdata();
        return $related_posts;
    }
} 