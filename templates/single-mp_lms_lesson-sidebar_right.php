<?php
use Mosaicpro\WP\Plugins\LMS\Courses;
$courses = Courses::getInstance();

get_header();
if (have_posts()) : while (have_posts()) : the_post();
    ?>

    <div class="row">
        <div class="col-md-9">

            <?php get_template_part($courses->getPrefix(), 'lesson-content'); ?>

        </div>
        <div class="col-md-3">

            <?php get_template_part($courses->getPrefix(), 'lesson-sidebar'); ?>

        </div>
    </div>
    <hr/>

<?php endwhile; endif; ?>
<?php get_footer(); ?>