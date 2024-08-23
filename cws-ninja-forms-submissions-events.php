<?php
/*
 * Plugin Name: CWS Ninja Forms Submissions Events
 * Plugin URI: https://www.crazywebstudio.co.th
 * Description: Retrieve NinjaForms submissions and post them as Events with a Draft status.
 * Version: 1.0.2
 * Author: Crazy Web Studio
 * Author URI: https://www.crazywebstudio.co.th
 * Text Domain: crazywebstudio-ninja-forms-submissions-events
 *
 * Copyright 2024 Crazy Web Studio
*/

class CWS_Ninja_Forms_Submissions_Events {

    const IMAGE_SIZE = 1080;
    const EXCERPT = 120;

    public function __construct() {
        add_action('ninja_forms_after_submission', [$this, 'log_ninja_form_submission']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'submissions-events-script',
            plugin_dir_url(__FILE__) . 'js/submissions-events-script.js',
            [],
            '1.0.0',
            true // Load in footer
        );
    }

    public function log_ninja_form_submission($form_data) {
        $form_id = self::get_option_by_name('form_id');
        if ($form_data['form_id'] == $form_id) {
            $attributes = array();

            foreach ($form_data['fields'] as $field) {
                $attributes[$field['key']] = $field['value'];
            }

            // Event action
            $this->create_new_event($attributes);
        }
    }

    private function create_new_event(array $formAttributes) {


        if (function_exists('tribe_events')) {

            $image = isset($formAttributes['event_images']) && is_array($formAttributes['event_images'])
                ? array_values($formAttributes['event_images'])[0] ?? ''
                : '';

            $date = $this->get_start_end_time($formAttributes);
            $organizers = self::get_option_by_name('organizer');
            $organizers_array = ($organizers) ? explode(',', $organizers ) : [];
            $title_field = self::get_option_by_name('title') ?? 'event_name';
            $description_field = self::get_option_by_name('description') ?? 'event_description';
            $venue_field = self::get_option_by_name('venue') ?? 'event_location';
            $cost_field = self::get_option_by_name('cost') ?? 'event_cost';


            $about_field = self::get_option_by_name('about') ?? 'about_event';
            $ticket_field = self::get_option_by_name('ticket_url') ?? 'ticket_url';
            $book_field = self::get_option_by_name('booking_url') ?? 'booking_url';

            $description_content = html_entity_decode( $formAttributes[$description_field] ?? '', ENT_QUOTES | ENT_HTML5);
            $about_content = html_entity_decode( $formAttributes[$about_field] ?? '', ENT_QUOTES | ENT_HTML5);

            /** This is varibles for set Custom fields name */
            $custom_ticket_url_field = self::get_option_by_name('custom_ticket_url') ?? 'ticket_url';
            $custom_booking_url_field = self::get_option_by_name('custom_booking_url') ?? 'book_url';
            $custom_about_field = self::get_option_by_name('custom_about_event') ?? 'about';

            // Event
            $args = [
                'title'             => $formAttributes[$title_field] ?? '',
                'start_date'        => $date['start_date'], // Format: ‘YYYY-MM-DD HH:MM:SS’
                'end_date'          => $date['end_date'],
                'cost'              => $formAttributes[$cost_field],
//                'currency_symbol'   => '$',
//                'currency_position' => 'prefix',
                'description'       => $description_content,
//                'url'               => $formAttributes['ticket_url'] ?? '',
//                'tag'               => ['concert'],
//                'category'          => [2],
//                'featured'          => 'yes',
                'excerpt'           => $this->excerpt_content( $description_content ) ?? '',
                'image'             => $this->resize_image_by_width_with_date($image) ?? 0, // The event featured image ID or URL
                'organizer'         => $organizers_array,
                'venue'             => $this->get_venue_id($formAttributes[$venue_field]),

                //This came from Custom fields
                "$custom_about_field"             => $about_content,
                "$custom_booking_url_field"       => $this->decode_url($formAttributes[$book_field]) ?? '',
                "$custom_ticket_url_field"        => $this->decode_url($formAttributes[$ticket_field]) ?? '',
            ];

            
            $args = $this->filter_arguments($args);
            $event_id = tribe_events()->set_args($args)->create()->ID;

           return $event_id;
        }
    }

    /**
     * @param $url
     * @return mixed|string
     */
    private function decode_url($url)
    {
        while (strpos($url, '&amp;') !== false) {
            $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
        }

        return $url;
    }

    /**
     * @param array $args
     * @return array
     */
    private function filter_arguments(array $args)
    {
        return array_filter($args, function($value) {
            return $value !== null && $value !== false && $value !== '';
        });

    }

    /**
     * @param $content
     * @return mixed|string|null
     */
    private function excerpt_content($content)
    {
        if($content){
            $sentences = $this->explode_by_delimiters($content);
            $excerpt = $sentences[0] ?? null;

            for ($count = 1; $count < count($sentences) && strlen($excerpt) < self::EXCERPT; $count++) {
                $excerpt .= " ".$sentences[$count];
            }
            return $excerpt;
        }
        return $content;
    }

    /**
     * @param $string
     * @return array|false|string[]
     */
    private function explode_by_delimiters($string) {
        $delimiters = ["\n\n", "<br>"];
        $regexPattern = '/' . implode('|', array_map('preg_quote', $delimiters)) . '/';
        return preg_split($regexPattern, $string);
    }


    /**
     * get fields data and name for mapping data
     * @return array
     */
    static public function get_option_by_name(string $field_name)
    {
        $options = self::get_options();
        return $options[$field_name];
    }
    static public function get_options(): array
    {
        $options = get_option('cws_ninja_forms_config');

        return [
            'form_id' => $options['form_id'] ?? 1,
            'organizer' => $options['organizer'] ?? '',
            'title' => $options['title'] ?? 'event_name',
            'date_time' => $options['date_time'] ?? 'event_date_time',
            'description' => $options['description'] ?? 'event_description',
            'venue' => $options['venue'] ?? 'event_location',
            'cost' => $options['cost'] ?? 'event_cost',
            'about' => $options['about'] ?? 'about_event',
            'ticket_url' => $options['ticket_url'] ?? 'ticket_url',
            'booking_url' => $options['booking_url'],
            'custom_ticket_url' => $options['custom_ticket_url'],
            'custom_booking_url' => $options['custom_booking_url'],
            'custom_about_event' => $options['custom_about_event'],
        ];
    }

    private function get_venue_id($venue_name)
    {
        $venues = tribe_venues()->all();
        $venue_id = 0;
        foreach ($venues as $venue) {
            if ($venue->post_title == $venue_name) {
                $venue_id = $venue->ID;
                break;
            }
        }

        /* Ger Venue Default in case no setting */
        $venue_id = empty($venue_id) ? $this->get_venue_default($venues) : $venue_id;

        return (int)$venue_id;
    }

    /**
     * @param $venues
     * @return int|mixed
     */
    private function get_venue_default($venues)
    {
        foreach ($venues as $venue) {
            return $venue->ID;
        }
        return 0;
    }

    private function get_start_end_time($formAttributes) {

        $date_time_field = self::get_option_by_name('date_time') ?? 'event_date_time';
        $date_time_end_field = self::get_option_by_name('date_time_end') ?? 'event_end_date_time';

        /** Prepare Start Event date/time */
        $date_string = $formAttributes[$date_time_field]['date'];
        $hour = $formAttributes[$date_time_field]['hour'];
        $minute = $formAttributes[$date_time_field]['minute'];
        $date_time = DateTime::createFromFormat('l, F d Y H i', "$date_string $hour $minute");
        // Format it to 'YYYY-MM-DD HH:MM:SS'
        $start_date = $date_time->format('Y-m-d H:i:s');

        /** Prepare End Event date/time */
        $end_date_string = $formAttributes[$date_time_end_field]['date'];
        $end_hour = $formAttributes[$date_time_end_field]['hour'];
        $end_minute = $formAttributes[$date_time_end_field]['minute'];
        $end_date_time = DateTime::createFromFormat('l, F d Y H i', "$end_date_string $end_hour $end_minute");
//        $end_date_time->modify('+1 day');
//        $end_date_time->setTime(3, 30);
        $end_date = $end_date_time->format('Y-m-d H:i:s');

        return array(
            'start_date' => $start_date,
            'end_date'   => $end_date
        );
    }

    private function resize_image_by_width_with_date($image_url) {

        $image_id = $this->get_image_id_from_url($image_url);

        if (!$image_id) {
            return null;
        }


        $new_width = self::IMAGE_SIZE;
        // Get the path to the original image
        $image_path = get_attached_file($image_id);

        // Create an instance of the WP_Image_Editor
        $image_editor = wp_get_image_editor($image_path);

        if (is_wp_error($image_editor)) {
            return $image_editor;
        }

        // Get the current dimensions
        $current_size = $image_editor->get_size();
        $current_width = $current_size['width'];
        $current_height = $current_size['height'];

        // Calculate the new height to maintain aspect ratio
        $new_height = ($new_width / $current_width) * $current_height;

        // Resize the image
        $image_editor->resize($new_width, $new_height, false);
        $saved_image = $image_editor->save($image_path);

        if (is_wp_error($saved_image)) {
            return $saved_image;
        }

        // Regenerate the image metadata to update WordPress with the new dimensions
        $attach_data = wp_generate_attachment_metadata($image_id, $image_path);
        wp_update_attachment_metadata($image_id, $attach_data);

        return $image_id;
    }

    private function get_image_id_from_url($image_url) {
        try {
            if ($this->is_valid_url($image_url)) {
                global $wpdb;
                $attachment_id = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' ORDER BY ID DESC LIMIT 1");
                return $attachment_id;
            }
            return null;

        } catch (Exception $e) {
            $error = $e->getMessage();
            $this->write_error_log($error);
            return null;
        }
    }


    private function is_valid_url($image_url) {
        return filter_var($image_url, FILTER_VALIDATE_URL) !== false;
    }

    private function write_error_log($error) {
        error_log($error.'\n', 3, plugin_dir_path(__FILE__) . 'ninja_forms_log.txt');
    }
}

// Initialize the plugin
new CWS_Ninja_Forms_Submissions_Events();

if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin-settings.php';
}


