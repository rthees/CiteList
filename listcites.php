<?php
/*
 Plugin name: ListCites
 Plugin URI: http://wuerzblog.de/
 Author: Ralf Thees
 Author URI: http://wuerzblog.de/
 Version: 0.1 alpha
 Description: shows randomly an quote marked in an article with [listcite] ... [/listcite]-tags.

 */

if (!defined('WP_CONTENT_URL'))
define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
if (!defined('WP_CONTENT_DIR'))
define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
if (!defined('WP_PLUGIN_URL'))
define('WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins');
if (!defined('WP_PLUGIN_DIR'))
define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
if (!defined('WP_LANG_DIR'))
define('WP_LANG_DIR', WP_CONTENT_DIR . '/languages');

if (!class_exists('listcites_class'))
{
	class listcites_class extends WP_Widget
	{
		var $start_tag='[listcite]';
		var $end_tag='[/listcite]';

		function listcites_class()
		{
			load_plugin_textdomain( 'listcites', false, dirname(plugin_basename(__FILE__)) .  '' );

			if (function_exists('register_uninstall_hook'))
			register_uninstall_hook(__FILE__, array(
			&$this,
                    'ondelete'
                    ));
                    $widget_ops = array(
                'classname' => 'listcites',
                'description' => __('Show marked quotes of articles', 'listcites')
                    );
                    $this->WP_Widget('listcites', __('ListCites'), $widget_ops);
		}

		function ondelete() {
			//echo "<h3>ondelete<h3><xpre>";
			global $wpdb;
			$cites = $wpdb->get_results("
			SELECT $wpdb->posts.ID 
			FROM $wpdb->postmeta,$wpdb->posts 
			WHERE meta_key='listcite' 
			AND $wpdb->posts.ID=$wpdb->postmeta.post_id 
			");
			
			foreach ($cites as $c) {
				//echo "<h4>$c->ID</h4>";
				delete_post_meta($c->ID,'listcite');
				$hit = $wpdb->get_results("
			SELECT $wpdb->posts.post_content 
			FROM $wpdb->posts 
			WHERE ID=$c->ID 
			");
			//$wpdb->show_errors();
			//$wpdb->print_error();
			$content=$hit[0]->post_content;
			$wpdb->query("UPDATE $wpdb->posts SET post_content = '".listcite_filter_tags($content)."' WHERE ID = $c->ID" );

			}
			echo "</xpre>";
		}

		function cite2db($post_ID) {
			global $post;
			$content=$_POST['post_content'];
			$old_meta=get_post_meta( $post_ID, 'listcite' );
			$pattern='/'.preg_quote($this->start_tag,'/').'(.*)'.preg_quote($this->end_tag,'/').'/isU';
			preg_match_all($pattern,$content,$cites);
			if (count($cites[1])>0) {
				foreach ($cites[1] as $c) {
					$strip_c[]=strip_tags($c);
				}
				update_post_meta($post_ID,'listcite',$strip_c);
			}

			if ((count($cites[1])==0) && $old_meta) {
				delete_post_meta($post_ID,'listcite');
			}
			//}
		}

		function cite_show() {
			$obj=$this->get_cites();
			echo '<blockquote>'.$obj->cite.'</blockquote><br /><cite><a href="'.$obj->link.'">'.$obj->author.', '.$obj->date.'</a></cite>';
		}

		function listcite_filter_tags($content) {
			$tags=Array('/'.preg_quote($this->start_tag,'/').'/','/'.preg_quote($this->end_tag,'/').'/');

			$reset=Array('','');
			return preg_replace($tags,$reset,$content);
		}

		function get_cites($mode=0) {
			global $wpdb;
			$cites = $wpdb->get_results("
			SELECT $wpdb->posts.ID, post_author,post_date_gmt, post_id, meta_value, post_status, user_nicename 
			FROM $wpdb->postmeta,$wpdb->posts, $wpdb->users 
			WHERE meta_key='listcite' 
			AND $wpdb->posts.ID=$wpdb->postmeta.post_id 
			AND post_author=$wpdb->users.ID 
			AND post_status = 'publish' 
			ORDER BY rand() LIMIT 1
			");
			//$wpdb->show_errors();
			//$wpdb->print_error();
			$out['id']=$cites[0]->ID;
			$out['author']=$cites[0]->user_nicename;
			$out['date']=strftime('%d.%m.%Y',strtotime($cites[0]->post_date_gmt));
			$cites=unserialize($cites[0]->meta_value);
			$cite=$cites[rand(0,count($cites)-1)];

			//$out =  new Object();
			$out['cite'] = $cite;
			$out['link']=get_permalink($out['id']);
			$this->citeobj=(object) $out;
			return (object) $out;
		}

		function on_delete()
		{
			delete_option('listcites');
		}

	} //Ende class listcites_class

} // END class_exists
$listcites=new listcites_class();

function listcite_show($mode=0) {
	global $listcites;
	//$a= $listcites->get_cites($mode);
	$listcites->cite_show($mode);
	//$listcites->xondelete();

}

function listcite_cite2db($post_ID) {
	global $listcites;
	$listcites->cite2db($post_ID);
}

function listcite_filter_tags($content) {
	global $listcites;
	return $listcites->listcite_filter_tags($content);
}



add_action('save_post', 'listcite_cite2db');
add_filter( "the_content", "listcite_filter_tags" )
?>