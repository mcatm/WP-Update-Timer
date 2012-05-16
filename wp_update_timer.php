<?php
/*
Plugin Name: WP Update Timer
Plugin URI: https://github.com/mcatm/WP-Update-Timer
Description: あらかじめセットした時刻に合わせて、項目をアップデートするWordPressのプラグインです。
Author: HAMADA, Satoshi (@mcatm)
Version: 0.1
Author URI: http://pelepop.com/
*/

// -- init --

if (!defined('WPUT_PLUGIN_BASENAME'))	define('WPUT_PLUGIN_BASENAME', plugin_basename(__FILE__));
if (!defined('WPUT_PLUGIN_NAME'))		define('WPUT_PLUGIN_NAME', trim(dirname(WPSM_PLUGIN_BASENAME),'/'));
if (!defined('WPUT_PLUGIN_DIR'))			define('WPUT_PLUGIN_DIR', WP_PLUGIN_DIR.'/'.WPSM_PLUGIN_NAME);
if (!defined('WPUT_PLUGIN_URL'))			define('WPUT_PLUGIN_URL', WP_PLUGIN_URL.'/'.WPSM_PLUGIN_NAME);
if (!defined('WPUT_DB_TABLENAME'))		define('WPUT_DB_TABLENAME', 'wp_wput_schedule');//table name

// -- do_update --

add_action('init', 'wput_do_update');

function wput_do_update() {
	if (!is_admin()) {
		global $wpdb;
		
		$result = $wpdb->get_results("SELECT * FROM `".WPUT_DB_TABLENAME."` WHERE `status` = 0 AND `datetime` < '".date('Y-m-d h:i:s')."'");
		
		if ($result) {
			foreach ($result as $row) {
				$post_id = $row->post_id;
				
				$arg = array();
				$tbl = "";
				switch ($row->field) {//posts
					case 'post_title':
					case 'post_content':
						$wpdb->update($wpdb->posts, array($row->field => $row->value), array('ID' => $post_id));
					break;
					
					default:
						add_post_meta($post_id, $row->field, $row->value, true);
						#$wpdb->update($wpdb->postmeta, array('meta_value' => $row->value), array('post_id' => $post_id, 'meta_key' => $row->field));
					break;
				}
				//echo $wpdb->last_query;exit;
				$wpdb->update(WPUT_DB_TABLENAME, array('status' => 1), array('ID' => $row->ID));
			}
		}
	}
}

// -- db --

add_action('edit_post', 'wput_set', 10);//投稿記事またはページが更新・編集された場合（コメント含む）
add_action('save_post', 'wput_set', 10);//インポート、記事・ページ編集フォーム、XMLRPC、メール投稿で記事・ページが作成・更新された場合
add_action('publish_post', 'wput_set', 10);//公開記事が編集された場合
add_action('transition_post_status', 'wput_set', 10);//記事が公開に変更された場合

function wput_set() {
	if (!empty($_POST['wput_datetime'])) {
		global $wpdb;
		$post_id = $_POST['post_ID'];
		$wpdb->query("DELETE FROM ".WPUT_DB_TABLENAME." WHERE post_id = '".$post_id."'");
		foreach ($_POST['wput_datetime'] as $k => $p) {
			$id = $wpdb->insert(WPUT_DB_TABLENAME, array(
				'datetime'			=> $_POST['wput_datetime'][$k],
				'field'				=> $_POST['wput_field'][$k],
				'value'				=> $_POST['wput_value'][$k],
				'post_id'			=> $post_id
			));
			//echo $wpdb->insert_id;
		}
	}
}


// -- add metabox --

function wput_init_adminmenu() {
	add_meta_box('update_schedule', __('更新スケジュール'), 'wput_output_metabox');
}
add_action('add_meta_boxes', 'wput_init_adminmenu');

function wput_output_metabox() {
	
	global $wpdb, $current_screen;
	
	#echo 'UUUUU';
	//print_r($current_screen);
	
	
	
	$dat = array();
	if (isset($_GET['post']) || isset($_POST['post_ID'])) {
		$post_id = (isset($_GET['post'])) ? $_GET['post'] : $_POST['post_ID'];
		$dat = $wpdb->get_results("SELECT * FROM ".WPUT_DB_TABLENAME." WHERE `post_id` = ".$post_id);
		$keys = get_post_custom_keys($post_id);
	}
	$dat[] = array('nodata');
?>

<style type="text/css">

</style>

<script type="text/javascript">
(function($) {
	$(function() {
		var name="wput_daybox";
		var name1="wput_daybox";
		var html= $(".wput_daybox").html();
		var dot ="."
		var named1;
		var named2;
		console.log(html);
		var cnt = 1;
		$(".wpsm_plus").live("click", function(){
					name="wput_daybox"+cnt;
					console.log(name);
					named1="."+name;
					named2="."+name1;

					console.log(named1);
					classn="<div class="+name+"></div>";
					$(named2).after(classn);
					$(named1).append(html);
					name1=name;
					cnt =cnt +1;
					console.log(name1);
			});
	});
})(jQuery);
</script>

<?php
	$c = 0;
	foreach($dat as $d):
?>

<div class="wput_daybox<?php echo $count?> box">
	<p class="datetime">
		<label>更新日時</label>
		<input type="text" name="wput_datetime[<?php echo $c?>]" size="50" tabindex="1" id="wput_time" autocomplete="off"<?php if(isset($d->datetime)):?> value="<?php echo $d->datetime?>"<?php endif;?> />
	</p>
	<p class="field">
		<label>更新項目</label>
		<select name="wput_field[<?php echo $c?>]">
			<option>-- 選択してください --</option>
			<option value="post_title"<?php if(isset($d->field) && $d->field == 'post_title'):?> selected="selected"<?php endif;?>>投稿タイトル</option>
			<option value="post_content"<?php if(isset($d->field) && $d->field == 'post_content'):?> selected="selected"<?php endif;?>>投稿本文</option>
			<?php foreach($keys as $key):if(!preg_match('(^_)', $key)):?><option value="<?php echo $key?>"<?php if(isset($d->field) && $d->field == $key):?> selected="selected"<?php endif;?>><?php echo $key?><?php endif;endforeach;?></option>
		</select>
		
		<?php /* <input type="text" name="wput_field[<?php echo $c?>]" size="50" tabindex="1" id="wput_field"<?php if(isset($d->field)):?> value="<?php echo $d->field?>"<?php endif;?> /> */ ?>
	</p>
	<p class="value">
		<label>更新内容</label>
		<textarea name="wput_value[<?php echo $c?>]"><?php if(isset($d->value)) {echo $d->value;}?></textarea>
	</p>
	<p><a class="wpsm_plus">+</a></p>
	<p><a class="wpsm_maenas">-</a></p>
</div>
<?php
	endforeach;
}

// -- install --

add_action('activate_' . WPUT_PLUGIN_BASENAME, 'wput_install');
function wput_install() {

	global $wpdb;

	if (strtolower($wpdb->get_var( "SHOW TABLES LIKE '".WPUT_DB_TABLENAME."'")) == strtolower(WPUT_DB_TABLENAME)) return;

	$charset_collate = '';
	if ($wpdb->has_cap('collation')) {
		if (!empty( $wpdb->charset)) $charset_collate = "DEFAULT CHARACTER SET ".$wpdb->charset;
		if (!empty( $wpdb->collate)) $charset_collate .= " COLLATE ".$wpdb->collate;
	}

	$wpdb->query("CREATE TABLE IF NOT EXISTS `".WPUT_DB_TABLENAME."` (
		`ID` BIGINT( 20 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`datetime` DATETIME NULL DEFAULT NULL ,
		`field` VARCHAR( 255 ) NULL DEFAULT NULL ,
		`value` TEXT NULL DEFAULT NULL ,
		`post_id` BIGINT( 20 ) NOT NULL,
		`status` TINYINT( 4 ) NOT NULL DEFAULT '0',
		INDEX ( `datetime`, `post_id`)
		) ".$charset_collate.";");

	return (strtolower($wpdb->get_var( "SHOW TABLES LIKE '".WPUT_DB_TABLENAME."'")) == strtolower(WPUT_DB_TABLENAME)) ? true:false;
}


?>