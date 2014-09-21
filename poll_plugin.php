<?php
/**
 * @package Poll_Plugin
 * @version 0.01
 */
/*
Plugin Name: Poll Plugin
Plugin URI: 
Description: This plugin that allows the user to take a poll as defined by the owner.
Author: Jeremy Katlic
Version: 0.01
Author URI:
*/
	
	// log files are in /etc/httpd/userLogs/j2.errors or j2.log
	
	//Plugin Variables
	global $wpdb; // is an instantiation of the class already set up to talk to the WordPress database
	global $poll_plugin_directory;
	
	//Questions Table Naming Variables
	global $poll_plugin_questions_table;
	global $poll_plugin_questions_table_version;
	$poll_plugin_questions_table = $wpdb->prefix . "questions";  // prefix is the assigned WordPress table prefix for the site.
	$poll_plugin_questions_table_version = 0.01;
	
	//Answers Table Naming Variables
	global $poll_plugin_answers_table;
	global $poll_plugin_answers_table_version;
	$poll_plugin_answers_table = $wpdb->prefix . "answers";  // prefix is the assigned WordPress table prefix for the site.
	$poll_plugin_answers_table_version = 0.01;
	
/*Activation Code
===================*/
	register_activation_hook(__FILE__, 'poll_plugin_activation');
	function poll_plugin_activation(){
		//will run code to activate the plugin
		global $wpdb;
		global $poll_plugin_directory;
		global $poll_plugin_questions_table;
		global $poll_plugin_questions_table_version;
		global $poll_plugin_answers_table;
		global $poll_plugin_answers_table_version;
		
	/* Create Tables
	=================*/
		$sql = "CREATE TABLE IF NOT EXISTS $poll_plugin_questions_table (
				question_id SERIAL NOT NULL,
				question_string TEXT NOT NULL,
				poll_name VARCHAR(35) NOT NULL,
				active INT(1) DEFAULT '1',
				PRIMARY KEY (question_id))";
				
		require_once(ABSPATH . "wp-admin/includes/upgrade.php");
		dbDelta( $sql );
		
		$sql = "CREATE TABLE IF NOT EXISTS $poll_plugin_answers_table (
				answer_id SERIAL NOT NULL,
				question_id BIGINT(20) NOT NULL,
				answer_string TEXT NOT NULL,
				clicked int(10) DEFAULT '0',
				rollover int(255) DEFAULT '0',
				PRIMARY KEY (answer_id, question_id))";
		dbDelta( $sql );
		
	/* Populate the Tables
	=======================*/
		update_option("poll_plugin_questions_table", $poll_plugin_questions_table);
		
		//empty the tables
		$wpdb->query("DELETE FROM $poll_plugin_questions_table");
		$wpdb->query("DELETE FROM $poll_plugin_answers_table");
		
		$insert = "INSERT INTO $poll_plugin_questions_table (
		`question_id` ,
		`question_string` ,
		`poll_name` ,
		`active`
		)
		VALUES (
		NULL , 'This is my first poll, do you like it?', 'First poll', '1'
		);";
		$wpdb->query($insert); 
		
		update_option("poll_plugin_answers_table", $poll_plugin_answers_table);
		$insert = "INSERT INTO $poll_plugin_answers_table (
		`answer_id` ,
		`question_id` ,
		`answer_string` ,
		`clicked`
		)
		VALUES (
		NULL , '1', 'Yes', '0'
		), (
		NULL , '1', 'No', '0'
		);";
		$wpdb->query($insert); 
	}

	add_action( 'wp_ajax_get_poll_plugin_data', 'get_poll_data' );
	function get_poll_data() {
		global $wpdb;
		global $poll_plugin_directory;
		global $poll_plugin_questions_table;
		global $poll_plugin_questions_table_version;
		global $poll_plugin_answers_table;
		global $poll_plugin_answers_table_version;
		
		$data = array();
		$data['error'] = "No Errors";
		$data['question'] = array();
		$data['answer'] = array();
		
		switch($_POST['type']){
			case 'get_data':
				$q = $wpdb->get_results($wpdb->prepare("SELECT * FROM $poll_plugin_questions_table WHERE question_id = %d", array($_POST['q_id'])), ARRAY_A);
				foreach($q as $key=>$value){
					$data['question'][$key] = $value;
				}
				unset($q);
				$q = $wpdb->get_results($wpdb->prepare("SELECT * FROM $poll_plugin_answers_table WHERE question_id = %d", array($_POST['q_id'])), ARRAY_A);
				foreach($q as $key1=>$value1){
					$data['answer'][$key1] = $value1;
				}
				break;
			case 'remove_ans':
				if(isset($_POST['q_id'])){
					if($_POST['q_id'] != '') $wpdb->query($wpdb->prepare("DELETE FROM $poll_plugin_answers_table WHERE answer_id = %d AND question_id = %d", array($_POST['a_id'], $_POST['q_id'])));
				}else{
					$data['error'] = "Failed to remove the answer.";
				}
				break;
			case 'remove_poll':
					if($_POST['q_id'] != ''){
						$wpdb->query(
							$wpdb->prepare("DELETE FROM $poll_plugin_questions_table WHERE question_id = %d", array($_POST['q_id']))
						);
						$wpdb->query(
							$wpdb->prepare("DELETE FROM $poll_plugin_answers_table WHERE question_id = %d", array($_POST['q_id']))
						);
					}else{
						$data['error'] = "Failed to remove the poll.";
					}
				break;
			case 'reset_count':
				if($_POST['q_id'] != ''){
						$wpdb->query(
							$wpdb->prepare("UPDATE $poll_plugin_answers_table SET clicked = 0 WHERE question_id = %d", array($_POST['q_id']))
						);
				}else{
					$data['error'] = "Failed to reset the poll count.";
				}
				break;
		}
		wp_send_json($data);
	}	


/*DeActivation Code
======================*/
	register_deactivation_hook(__FILE__, 'poll_plugin_deactivation');
	function poll_plugin_deactivation(){
		//will run code to activate the plugin
		global $wpdb;
		global $poll_plugin_directory;
		global $poll_plugin_questions_table;
		global $poll_plugin_questions_table_version;
		global $poll_plugin_answers_table;
		global $poll_plugin_answers_table_version;
	/* Drop Tables
	=================*/
		$sql = "DROP TABLE IF EXISTS $poll_plugin_questions_table";
		$wpdb->query($sql);
		
		$sql = "DROP TABLE IF EXISTS $poll_plugin_answers_table";
		$wpdb->query($sql);
		
		delete_option("poll_plugin_questions_table", $poll_plugin_questions_table);
		delete_option("poll_plugin_questions_table", $poll_plugin_answers_table);
	}
	
/* Administration Menu Creation
===================================*/
	//tree of function calls to add to admin menu
	add_action('admin_menu', 'poll_plugin_admin_action');
	function poll_plugin_admin_action(){
		$my_settings_page = add_options_page('Poll Wizard', "Poll Wizard", "manage_options", __FILE__, "poll_plugin_admin_page"); 
		
		//Load script resources into the admin page head for form functionality
		add_action( 'admin_footer', 'poll_plugin_admin_footer_script' ); //didn't like the -{$my_settings_page}
		function poll_plugin_admin_footer_script() {?>
			<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>';
			<script type="text/javascript">
				$(document).ready(function(e){
					count = 3;
					
					$('#removePoll').click(function(e){
						id = $('#old_option').val();
						if(id != ''){
							var data = {
								action: 'get_poll_plugin_data',
								type: 'remove_poll',
								q_id: id
							};
							$.post(ajaxurl, data, function(form_data){window.location.href=window.location.href;});
							
						}
					});
					
					$('#resetPoll').click(function(e){
						id = $('#old_option').val();
						if(id != ''){
							var data = {
								action: 'get_poll_plugin_data',
								type: 'reset_count',
								q_id: id
							};
							$.post(ajaxurl, data, function(form_data){window.location.href=window.location.href;})
							.error(function(e){alert("failed");});
						}else{ alert("no id"); }
					});
					
					$('#add_ans').click(function(e){
						$( '.ans').last().after( "<tr class='ans' id= 'ans" + count + " '><td><label>Answer</label></td><td><input type= 'text ' name= 'answer[] '/><i class= 'fa fa-minus-square fa-2x remove '></i></td></tr>");
						count++;
					});
					
					$( '#poll_table').on( 'click',  '.remove', function(e){
						if(count > 1){
							$(this).closest('.ans').remove(); //use the id rather than the class so that it can remove any position
							if($('#old_option').val() != ''){
								var data = {
									action: 'get_poll_plugin_data',
									type: 'remove_ans',
									a_id: $(this).prev().val(),
									q_id: $('#old_option').val()
								};
								$.post(ajaxurl, data, function(form_data){});
							}
							count--;
						}
					});
					
					$('#old_option').change(function(e){
						id = $(this).val();
						give_back = '';
						if(id != ''){
							var data = {
								action: 'get_poll_plugin_data',
								type: 'get_data',
								q_id: id
							};
							$.post(ajaxurl, data, function(form_data){
								/*returns (question_id question_string poll_name) */
								$('#name').val(form_data['question'][0]['poll_name']);
								$('#question').val(form_data['question'][0]['question_string']);
								if(form_data['question'][0]['active'] == 1) $('#activePoll').prop('checked', true);
								else $('#activePoll').prop('checked', false);
								count = 1;
								for(i = 0; i < form_data['answer'].length; i++){
									/*returns (answer_id question_id answer_string clicked) */
									give_back +='<tr class="ans" id="ans'+ count +'">';
									give_back +='	<td><label>Answer</label></td>';
									give_back +='	<td><input type="text" name="answer[]"  value="' + form_data['answer'][i]['answer_string'] + '" />';
									give_back += '<input name="ans_id[]" class="ans_id" value="' + form_data['answer'][i]['answer_id'] + '" hidden />';
									if(count > 1) give_back += '<i class="fa fa-minus-square fa-2x remove" style="cursor: crosshair"></i>';
									give_back += '&nbsp;&nbsp;&nbsp;&nbsp;<label>Clicked ' + form_data['answer'][i]['clicked'];
									if(form_data['answer'][i]['clicked'] == 1) give_back += ' time';
									else give_back += ' times';
									give_back += '</label></td></tr>';
									count++;
								}
								$('.ans').remove();  //clear out all answers on the page currently
								$('.ans_top').after(give_back);		//add all the answers we just got from the DB
							})
							.error(function(p){
								alert('error');
							});
						}else{
							$('#name').val('');
							$('#question').val('');
							
							give_back +='<tr class="ans" id="ans1" >';
							give_back +='	<td><label>Answer</label></td>';
							give_back +='	<td><input type="text" name="answer[]" /></td>';
							give_back +='</tr>';
							give_back +='<tr class="ans" id="ans2">';
							give_back +='	<td><label>Answer</label></td>';
							give_back +='	<td><input type="text" name="answer[]" /><i class="fa fa-minus-square fa-2x remove" style="cursor: crosshair"></i></td>';
							give_back +='</tr>';
							count = 3;
							$('.ans').remove();
							$('.ans_top').after(give_back);
						}
					});
				}); 
            </script>
			<link href="http://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css" rel="stylesheet" />
			<?php
		}
		
		
	}
	
	/* Create admin page form
	==========================*/
	function poll_plugin_admin_page(){
		global $wpdb;
		global $poll_plugin_directory;
		global $poll_plugin_questions_table;
		global $poll_plugin_questions_table_version;
		global $poll_plugin_answers_table;
		global $poll_plugin_answers_table_version;
		
		if(isset($_POST['old_option'])){
			if($_POST['old_option'] == ''){
				//insert a new table
				$wpdb->query($wpdb->prepare("INSERT INTO $poll_plugin_questions_table (question_string, poll_name) VALUES (%s, %s)", array($_POST['question'], $_POST['poll_name'])));
				
				//get id from that previously inserted value
				$poll_id = $wpdb->get_row($wpdb->prepare("SELECT question_id FROM $poll_plugin_questions_table WHERE question_string = %s AND poll_name = %s", array($_POST['question'], $_POST['poll_name'])), ARRAY_A, 0);
				$poll_id = $poll_id['question_id'];
				
				if(isset($_POST['active'])){
					$wpdb->query("UPDATE $poll_plugin_questions_table SET active = 0");
					$wpdb->query($wpdb->prepare("UPDATE $poll_plugin_questions_table SET active = 1 WHERE question_id = %d", array($poll_id)));
				}
				
				foreach($_POST['answer'] as $key=>$value){
					$wpdb->query($wpdb->prepare("INSERT INTO $poll_plugin_answers_table (`answer_id`, `question_id`, `answer_string`)
					VALUES (NULL , %d, %s)", array($poll_id, $value)));
				}
			}else{
				//handle updating the table
				$wpdb->query($wpdb->prepare("UPDATE $poll_plugin_questions_table SET question_string = %s, poll_name = %s WHERE question_id = %d", 
				array($_POST['question'], $_POST['poll_name'], $_POST['old_option']))); 
				$loop_count = 0;
				foreach($_POST['answer'] as $key=>$value){
					$t = count($_POST['answer']) - count($_POST['ans_id']);
					if(count($_POST['answer']) - $t <= $loop_count){
					//runs an insert
						$wpdb->query($wpdb->prepare("INSERT INTO $poll_plugin_answers_table(answer_string, question_id) VALUES (%s, %d)", 
						array($value, $_POST['old_option'])));
					}else{ 
					 //runs an update
						$wpdb->query($wpdb->prepare("UPDATE $poll_plugin_answers_table SET answer_string = %s WHERE question_id = %d AND answer_id = %d", 
						array($value, $_POST['old_option'], $_POST['ans_id'][$key])));
					}
					$loop_count++;
				}
				
				if(isset($_POST['active'])){
					$wpdb->query("UPDATE $poll_plugin_questions_table SET active = 0");
					$wpdb->query($wpdb->prepare("UPDATE $poll_plugin_questions_table SET active = 1 WHERE question_id = %d", array($_POST['old_option'])));
				}
			}
		}
		?>
        <div class="wrap">
            <form id="main_form" method="post" action="<?php print $_SERVER['REQUEST_URI']?>">
                <table id="poll_table">
                    <tr>
                        <td><label>Choose to edit</label></td>
                        <td>
                            <select name="old_option" id="old_option">
                                <option></option>
                                <?php
									$options = $wpdb->get_results("SELECT question_id, poll_name FROM $poll_plugin_questions_table", ARRAY_A);
									foreach($options as $option){
										print "<option value = '{$option['question_id']}'>{$option['poll_name']}</option>";
									}
								?>
                            </select>
                            <input type="checkbox" id="activePoll" name="active"/> Active Poll
                        </td>
                    </tr>
                    <tr>
                        <td><label>poll Name</label></td>
                        <td><input type="text" name="poll_name" id="name"/></td>
                    </tr>
                    <tr class="ans_top">
                        <td><label>Question</label></td>
                        <td><textarea name="question" id="question"></textarea></td>
                    </tr>
                    <tr class="ans" id="ans1">
                        <td><label>Answer</label></td>
                        <td><input type="text" name="answer[]" /></td>
                    </tr>
                    <tr class="ans" id="ans2">
                        <td><label>Answer</label></td>
                        <td><input type="text" name="answer[]" /><i class="fa fa-minus-square fa-2x remove" style="cursor: crosshair"></i></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td><p><i class="fa fa-plus-square fa-2x" id="add_ans" style="cursor: crosshair"></i> Answer</p></td>
                    </tr>
                     <tr>
                        <td><input type="submit"/></td>
                        <td><input type="button" id="removePoll" value="Remove Poll" /> <input type="button" id="resetPoll" value="Reset Poll Count" /></td>
                    </tr>
                </table>
            </form>
            <p>To show the active poll on your page just add the shortcode [show-my-poll-plugin] wherever you want the poll to show and it will do the rest!</p>
        </div>
        <?php
	}
	
	/* Add shortcode and process it */
	add_shortcode("show-my-poll-plugin", "poll_plugin_shortcode");
	
	function poll_plugin_shortcode($att){
		global $wpdb;
		global $poll_plugin_directory;
		global $poll_plugin_questions_table;
		global $poll_plugin_questions_table_version;
		global $poll_plugin_answers_table;
		global $poll_plugin_answers_table_version;
		?>
		<div class="wrap">
        	<?php
			//HANDLE POST DATA FROM POLL
				if(isset($_POST['question_id'])){
					if(isset($_POST['answer'])){ //DOUBLE CHECKED FOR SECURITY!!!
						//up the count for that answer
						if($_POST['answer'] != null){
							$wpdb->query(
								$wpdb->prepare("UPDATE $poll_plugin_answers_table SET clicked = clicked + 1 WHERE question_id = %d AND answer_id = %d",
								array($_POST['question_id'], $_POST['answer']))
							);
						}
					}
				}
			?>
			<?php
			//BUILD THE POLL
				$poll_data = $wpdb->get_results("SELECT * FROM $poll_plugin_questions_table", ARRAY_A);
				foreach($poll_data as $option){
					if($option['active'] == 1){
						$total_clicks = 0; //used to calculate the percentages
						print "<h3>{$option['question_string']}</h3>";
						print "<form method=\"post\">";
						print "<input type='text' name='question_id' value='{$option['question_id']}' hidden />";
						$answer_data = $wpdb->get_results("SELECT * FROM $poll_plugin_answers_table WHERE question_id = {$option['question_id']}", ARRAY_A);
						foreach($answer_data as $temp){ $total_clicks += $temp['clicked']; } //gets the total clicks so that it can be used to calculate percentages
						foreach($answer_data as $temp){	//print out the answers and their percentages
							print "<input type=\"radio\" name=\"answer\" value=\"{$temp['answer_id']}\" /> ";
							print " <label>{$temp['answer_string']}</label> | <label>"; 
							if($temp['clicked'] > 0) print round(($temp['clicked']/$total_clicks)*100);
							else print "0";
							print "%</label><br/>";
						}
						print "<br/><input type='submit' value='Vote!'/>";
						print "</form>";
					}
				}
			?>
		</div> 
		<?php
	}
?>