<?php
/**
 * Plugin Name: Vivir na Coruña Api
 * Plugin URI: http://vivirnacoruna.es
 * Description: API REST  para Vivir na Coruña
 * Version: 1.0
 * Author: Denís Graña
 * Author URI: http://me.denisgranha.com
 * Requires at least: 3.7
 * Tested up to: 4.0
 */


function hello_world()
{
    add_rewrite_rule(
        '^api/v1/(\w+)(.*)?',
        'index.php?api_action=$matches[1]&route=$matches[2]',
        'top'
    );
}
add_action('init','hello_world');

/**
 * Determine if the rewrite rules should be flushed.
 */
function api_maybe_flush_rewrites() {
    flush_rewrite_rules();
}
add_action( 'init', 'json_api_maybe_flush_rewrites', 999 );

function custom_query_vars($vars){
    $vars[] = 'api_action';
    $vars[] = "route";
    return $vars;
}
add_filter('query_vars', 'custom_query_vars');


add_filter( 'posts_where', 'wpse18703_posts_where', 10, 2 );
function wpse18703_posts_where( $where, &$wp_query )
{
    global $wpdb;
    if ( $wpse18703_title = $wp_query->get( 'wpse18703_title' ) ) {
        $where .= ' AND ' . $wpdb->posts . '.post_title LIKE \'%' . esc_sql( $wpdb->esc_like( $wpse18703_title ) ) . '%\'';
    }
    return $where;
}




add_action('parse_request', 'custom_requests');
function custom_requests ( $wp ) {

    $valid_actions = array('today', 'event', 'categories','prices','search','interval');

    if(
        !empty($wp->query_vars['api_action']) &&
        in_array($wp->query_vars['api_action'], $valid_actions)
    ) {

        header("Access-Control-Allow-Origin: *");

        //$wp->query_vars,
        //$_GET

        /**
         * Todos los eventos del día
         */
        if($wp->query_vars['api_action'] == 'today'){
            $today = strtotime("today");

            $tomorrow = strtotime("tomorrow");



            $query =  array(
                'post_type' => 'ajde_events',
                'meta_query' => array(
                    array(
                        'key' => 'evcal_srow',
                        'value' => $today,
                        'compare' => ">="
                    ),
                    array(
                        'key' => 'evcal_erow',
                        'value' => $tomorrow,
                        'compare' => "<="
                    )
                )
            );


            $query = new WP_Query($query);
            $eventos = $query->get_posts(
                array(
                    'posts_per_page'   => 50)
            );
            foreach($eventos as $evento){
                $evento->end_date = get_post_meta($evento->ID,"evcal_erow",true);
                $evento->start_date = get_post_meta($evento->ID,"evcal_srow",true);
                $evento->location = get_post_meta($evento->ID,"evcal_location",true);
                $evento->location_name = get_post_meta($evento->ID,"evcal_location_name",true);
                $evento->categories = wp_get_post_terms( $evento->ID,'event_type');
                $evento->price_type = wp_get_post_terms( $evento->ID,'event_type_2');
                $evento->event_types = wp_get_post_terms( $evento->ID,'event_type');
                $evento->price =  get_post_meta( $evento->ID,'_evcal_ec_f1a1_cus',true);
                $evento->image = wp_get_attachment_url( get_post_thumbnail_id( $evento->ID ));

            }


            wp_send_json($eventos);
        }


        /**
         * Datos de Evento por ID
         */
        if($wp->query_vars['api_action'] == 'event'){

            preg_match("/(\d+)/",$wp->query_vars['route'],$matches);
            $id = $matches[0];

            $evento = get_post($id,"OBJECT");
            $evento->end_date = get_post_meta($evento->ID,"evcal_erow",true);
            $evento->start_date = get_post_meta($evento->ID,"evcal_srow",true);
            $evento->location = get_post_meta($evento->ID,"evcal_location",true);
            $evento->location_name = get_post_meta($evento->ID,"evcal_location_name",true);
            $evento->categories = wp_get_post_terms( $evento->ID,'event_type');
            $evento->price_type = wp_get_post_terms( $evento->ID,'event_type_2');
            $evento->event_type = wp_get_post_terms( $evento->ID,'event_type');
            $evento->price =  get_post_meta( $evento->ID,'_evcal_ec_f1a1_cus',true);
            $evento->image = wp_get_attachment_url( get_post_thumbnail_id( $evento->ID ));


            wp_send_json(
                $evento
            );
        }


        /**
         * Tipos de Evento
         */
        if($wp->query_vars['api_action'] == 'categories'){
            //wp_send_json(get_terms( array("event_type")));

            $categories = array();
            $childs = array();

            $excluded = array(297,1170,272,255,269,335);

            foreach(get_terms( array("category")) as $category){
                if($category->parent == 0){
                    $category->childs = array();
                    $categories[$category->term_id] = $category;
                }
                else{
                    $childs[] = $category;
                }

            }

            foreach($childs as $child){
                $categories[$child->parent]->childs[] = $child;
            }

            wp_send_json(
                $categories
            );
        }


        /**
         * Tipos de Prezos de evento
         */
        if($wp->query_vars['api_action'] == 'prices'){
            wp_send_json(get_terms( array("event_type_2")));
        }

        /**
         * Búsqueda por nome eventos que teñen lugar de hoxe en diante
         */
        if($wp->query_vars['api_action'] == 'search'){

            preg_match("/(\w+)/",$wp->query_vars['route'],$matches);

            $query_name = $matches[0];
            $today = strtotime("today");


            $args = array(
                'post_type' => 'ajde_events',
                'wpse18703_title' => $query_name,
                'post_status'      => 'publish',
                'orderby'          => 'title',
                'order'            => 'ASC',
                'meta_query' => array(
                    array(
                        'key' => 'evcal_srow',
                        'value' => $today,
                        'compare' => ">="
                    )
                )

            );
            $query = new WP_Query($args);
            $eventos = $query->get_posts(
                array(
                    'posts_per_page'   => 50)
            );


            foreach($eventos as $evento){
                $evento->end_date = get_post_meta($evento->ID,"evcal_erow",true);
                $evento->start_date = get_post_meta($evento->ID,"evcal_srow",true);
                $evento->location = get_post_meta($evento->ID,"evcal_location",true);
                $evento->location_name = get_post_meta($evento->ID,"evcal_location_name",true);
                $evento->categories = wp_get_post_terms( $evento->ID,'event_type');
                $evento->price_type = wp_get_post_terms( $evento->ID,'event_type_2');
                $evento->event_type = wp_get_post_terms( $evento->ID,'event_type');
                $evento->price =  get_post_meta( $evento->ID,'_evcal_ec_f1a1_cus',true);
                $evento->image = wp_get_attachment_url( get_post_thumbnail_id( $evento->ID ));
            }


            wp_send_json(
                $eventos
            );
        }

        /**
         * Búsqueda por de eventos por intervalo de tiempo
         */
        if($wp->query_vars['api_action'] == 'interval') {

            preg_match("/(\d+)\/(\d+)/", $wp->query_vars['route'], $matches);

            $start = $matches[1];
            $end = $matches[2];

            $query =  array(
                'post_type' => 'ajde_events',
                'meta_query' => array(
                    array(
                        'key' => 'evcal_srow',
                        'value' => $start,
                        'compare' => ">="
                    ),
                    array(
                        'key' => 'evcal_erow',
                        'value' => $end,
                        'compare' => "<="
                    )
                )
            );


            $query = new WP_Query($query);
            $eventos = $query->get_posts(
                array(
                    'posts_per_page'   => 50
                )
            );
            foreach($eventos as $evento){
                $evento->end_date = get_post_meta($evento->ID,"evcal_erow",true);
                $evento->start_date = get_post_meta($evento->ID,"evcal_srow",true);
                $evento->location = get_post_meta($evento->ID,"evcal_location",true);
                $evento->location_name = get_post_meta($evento->ID,"evcal_location_name",true);
                $evento->categories = wp_get_post_terms( $evento->ID,'event_type');
                $evento->price_type = wp_get_post_terms( $evento->ID,'event_type_2');
                $evento->event_types = wp_get_post_terms( $evento->ID,'event_type');
                $evento->price =  get_post_meta( $evento->ID,'_evcal_ec_f1a1_cus',true);
                $evento->image = wp_get_attachment_url( get_post_thumbnail_id( $evento->ID ));

            }


            wp_send_json($eventos);

        }



        }
}

