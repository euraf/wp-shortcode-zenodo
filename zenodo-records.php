<?php
/*
Plugin Name: Zenodo Records Shortcode
Description: A WordPress plugin to retrieve and display Zenodo records using a shortcode that links to Zenodo API. Developed under te EU project www.digitaf.eu, contract number 101059794
Version:     1.0
Author:      João HN Palma, <a href="https://orcid.org/0000-0002-1391-3437" target="_blank">ORCID</a>, <a href="https://mvarc.eu" target="_blank">MVARC</a>, suported by <a href="https://digitaf.eu" target="_blank">DigitAF project</a>
*/

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}


// Shortcode function to fetch and display Zenodo records
function zenodo_records_shortcode($atts) {
    
    // Extract shortcode attributes and set default values
    //
    $atts = shortcode_atts(
        array(
            'query' => 'euraf&sort=mostviewed', // Default query for most viewed of the European Agroforestry federation 
            // you can use the grants.code field, e.g.
            // 'query' => 'grants.code:101059794 AND resource_type.type:publication"', // Default query
            
        ),
        $atts,
        'zenodo_records' // Shortcode name
    );
    // Encode the query parameter for use in the API URL
    $query = urlencode($atts['query']);
    #$query = urlencode('grants.acronym:DIGITAF AND resource_type.type:publication AND "Policy Briefing"');
    $api_url = 'https://zenodo.org/api/records/?q=' . $query.'&sort=mostrecent&order=asc';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'User-Agent: Mozilla/5.0 (WordPress Plugin)'));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return 'cURL error: ' . esc_html(curl_error($ch));
    }

    curl_close($ch);
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['hits']['hits'])) {
        return '<p>No records found for this query.</p>';
    }
    #var_dump($data);
    $output = '<div class="zenodo-records-container">';

    foreach ($data['hits']['hits'] as $record) {
        $title = $record['metadata']['title'] ?? 'No title';
        $raw_description = $record['metadata']['description'] ?? 'No description';
        $description = esc_html(wp_kses_post(wp_trim_words(strip_tags($raw_description), 50, '...')));
        //$description = substr($record['metadata']['description'] ?? 'No description', 0, 300) . '...';
        $url = $record['doi_url'] ?? '#';
        $download_count = $record['stats']['unique_downloads'] ?? 0;
        $view_count = $record['stats']['unique_views'] ?? 0;
        $record_id = $record['id'] ?? '#';
        $pdf_filename = esc_url($record['files'][0]['key']) ?? 0;
        $first_published_timestamp = $record['metadata']['publication_date'] ?? '#';
        //$first_published_timestamp = $record['modified'] ?? '#';
        $last_updated_timestamp = $record['updated'] ?? '#';
        $first_published = date('jS M Y', strtotime($first_published_timestamp)); 
        $last_updated = date('jS M Y', strtotime($last_updated_timestamp)); 

        $pdf_thumb_url = "https://zenodo.org/record/".$record_id."/thumb100";// 10, 50, 100, 250, 750, 1200
        $thumbnail_html = '<img src="' . esc_url($pdf_thumb_url) . '" onerror="this.src=\'' . plugins_url('pdf-file.png', __FILE__) . '\';" alt="Thumbnail" class="zenodo-thumbnail" />';
     
        
        // Calculate the number of fire icons based on view count
        $fire_icons = '';
        if ($view_count >= 100) {
            $fire_count = floor($view_count / 100); // 1 fire icon for every 100 views
            if ($fire_count>5) {$fire_count=5;}// truncate to 5 icons
            
            for ($i = 0; $i < $fire_count; $i++) {
               $fire_icons .= '&#128175;'; // This renders the 100 emoji (💯)
                
            }
        }
        // Add "Hot Topic!" badge if view count exceeds a certain threshold (e.g., 500 views)
        $hot_topic_badge = '';
        if ($view_count >= 300) {
            $hot_topic_badge = '<span class="hot-topic-badge">Hot Topic!</span>';
        }

        // Create card HTML
        $output .= '<div class="zenodo-record-card">';
        $output .= '<div class="zenodo-record-title-thumbnail">';
        if (!empty($thumbnail_html)) {
            // Add thumbnail (if it exists)
            $output .= '<div class="zenodo-record-thumbnail">' . $thumbnail_html . '</div>';
        }

        // Add the title
        $output .= '<div class="zenodo-record-title">';
        $output .= '<h4><a href="' . esc_url($url) . '" target="_blank">' . esc_html($title) . '</a></h4>';
        $output .= '</div>';

        $output .= '</div>'; // Close title-thumbnail container

        // Description
        $output .= '<div class="zenodo-record-description">';
        $output .= '<p class="description">' . $description . '</p>'; 
        $output .= '</div>'; // Close description section

        // Read more link with download and view count
        $output .= '<a href="' . esc_url($url) . '" target="_blank" class="zenodo-record-link">Read more... ';
        $output .= '<i class="fas fa-download" title="Downloads"></i> ' . $download_count . ' ';
        $output .= '<i class="fas fa-eye" title="Views"></i> ' . $view_count;


        // Add "Hot Topic!" badge if view count exceeds a threshold
        if ($view_count >= 500) {
            $output .= '<span class="hot-topic-badge">Hot Topic!</span>';
        }

        $output .= '</a>';

        // Add fire icons for each 100 views
        $output .= '<span>   ' . $fire_icons . '</span>';

        // Timestamp placeholder at the bottom right
        $output .= '<div class="timestamp" style="position: absolute; bottom: 10px; right: 10px;">';
        $output .= '<span title="Published"><b>P:</b></span> '.$first_published.', <span title="Updated"><b>U</b></span>: '. $last_updated; // You can replace this with an actual timestamp
        $output .= '</div>';

        // Close the record card div
        $output .= '</div>';
    }

    $output .= '</div>';
    return $output;
}

// Register the shortcode
add_shortcode('zenodo_records', 'zenodo_records_shortcode');

// Enqueue FontAwesome and custom CSS
function zenodo_records_enqueue_styles() {
    wp_enqueue_style('zenodo-records-fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4');
    wp_enqueue_style('zenodo-records-style', plugins_url('style.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'zenodo_records_enqueue_styles');

function check_image_exists($url) {
    $context = stream_context_create(array(
        'http' => array(
            'method' => 'HEAD', // Use HEAD request to just get headers
            'timeout' => 2 // Set timeout to 2 seconds
        )
    ));

    $headers = @get_headers($url, 1, $context);
    if ($headers && strpos($headers[0], '200') !== false && strpos($headers['Content-Type'], 'image/') !== false) {
        return true;
    }
    return false;
}
