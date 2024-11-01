<?php
/*
Plugin Name: Simple Preview
Plugin URI: http://gabriel.nagmay.com/2009/10/simple-preview/
Description: Enables you to give a link to anonymous users for public preview of a post before it is published.
Author: Gabriel Nagmay
Version: 0.1.9.1
Author URI: http://gabriel.nagmay.com

/*
	Where credit is due: This plugin is a updated/modified/simplified version of Public Post Preview 1.3 
	by Matt Martz and Jonathan Dingman
	
	Core Changes: 
		-	Completely removed the use of "nonce" variable. Posts marked preview are now accessible by multiple 
			anonymous users. There is no longer a 24hr time limit.
		
		- 	Public_Post_Preview used the option variable as an array to store preview state - genius! 
			However, it's use seemed backwards with an empty item == preview true. This logic was reversed 
			so: simple_array[,,true,,true] now means that p=1 and p=4 have previews.
			
			This also allows for the preview to be turned off by default. 
		
		-	Removed extra variables for a cleaner URI: http://www.yourblog.com/?p=1502&preview=true

 	Copyright 2009  Gabriel Nagmay (email : gabriel@nagmay.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

// */

class Simple_Preview {

	// Variable place holder for post ID for easy passing between functions
	var $id, $post_type;

	// Plugin startup
	function __construct() {
		if ( ! is_admin() ) {
			add_action('init', array(&$this, 'show_preview'));
		} else {
			register_activation_hook(__FILE__, array(&$this, 'init'));
			add_action('admin_menu', array(&$this, 'meta_box'));
			add_action('save_post', array(&$this, 'save_post'));
		}
	}
	
	// Initialize plugin
	function init() {
		if ( ! get_option('simple_preview') )
			add_option('simple_preview', array());
	}

	// Register meta box
	function meta_box() {
			add_action( 'post_submitbox_misc_actions', array(&$this, 'post_submitbox_misc_actions' ) );
	}
	
	// checkbox
	function post_submitbox_misc_actions($post) {		
		$preview_posts = get_option('simple_preview');
		echo '<div class="misc-pub-section simple-preview">';
		if (!in_array($post->post_status, array('publish')) ) {
			$checked = isset($preview_posts[$post->ID]) ? 'checked="checked"' : ''; // is it checked
			echo '<label for="public_preview_status" class="selectit">
					<input type="checkbox" name="public_preview_status" id="public_preview_status" value="on"'.$checked.'/> Allow preview
				  </label>';
			if ( isset($preview_posts[$post->ID]) ) {
				$this->id = (int) $post->ID;
                // Create link that matches the regulare preview link
                if($post->post_type == 'page'){
				    $url = htmlentities(add_query_arg(array('page_id' => $this->id, 'preview' => 'true'), get_option('home') . '/'));
                }
                else{
				    $url = htmlentities(add_query_arg(array('p' => $this->id, 'preview' => 'true'), get_option('home') . '/'));
                }
				echo "</br><a href=\"$url\" target=\"_blank\">$url</a>";
			}
		} else {
			echo '<!--Preview is not available-->'; // Post is published. Preview not available, but we don't need to say it. 
		}
		echo '</div>';
		
	}

	// Update options on post save
	function save_post($post) {
        if( empty($_POST['post_ID']) ){ return; } // ignore autodraft

		$preview_posts = get_option('simple_preview');
		$post_id = $_POST['post_ID'];
		
		if ( $post != $post_id )
			return;

		if ( !isset($_POST['public_preview_status']) || $_POST['public_preview_status']!='on') {
			unset($preview_posts[$post_id]); // public_preview_status not set
		}
		else if( in_array($_POST['post_status'], array('publish')) && !in_array($_POST['publish'], array('Schedule')) ){
			unset($preview_posts[$post_id]); // published (and not scheduled)
	    } 
		else{
			$preview_posts[$post_id] = true;
		}
		update_option('simple_preview', $preview_posts); 
	}

	// Show the post preview
	function show_preview() {
		if ( !is_admin() && (isset($_GET['p']) || isset($_GET['page_id'])) && isset($_GET['preview']) ) {
			$this->id = (int) isset($_GET['p']) ? $_GET['p'] : ($_GET['page_id']); 
			$preview_posts = get_option('simple_preview');	
		
			// page does not exist
			if( !get_post_status($this->id)){
				wp_die('Preview not available.'); 
			}
			// preview not set
			if ( !isset($preview_posts[$this->id]) && !current_user_can('edit_post', $this->id) ){
				wp_die('Preview not available.');  
			}
			
			// Add support for pages & custom post typess
			$oPost = get_post( $this->id );
			$this->post_type = $oPost->post_type;
			add_action( 'pre_get_posts', array( $this, 'add_post_type_to_query' ) );
			
			// Show the preview	
			add_filter('posts_results', array(&$this, 'fake_publish'));
		}
	}
	
	// Add post type to query when showing the preview
	function add_post_type_to_query( &$query ) {
    	$query->set( 'post_type', $this->post_type );     
	}

	// Fake the post being published so we don't have to do anything *too* hacky to get it to load the preview
	function fake_publish($posts) {
        if(!empty($posts)){
            $posts[0]->post_status = 'publish';
            return $posts;
        }
	}
}

$Simple_Preview = new Simple_Preview();

?>