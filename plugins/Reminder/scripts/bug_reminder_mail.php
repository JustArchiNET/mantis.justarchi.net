<?php
# This page tests sending an E-mail if a due date is getting near
if( php_sapi_name() != 'cli' ) {
	echo "It is not allowed to run this script with PHP SAPI name: " . php_sapi_name() . ".\n";
	exit( 1 );
}

# No real email is sent nor are notes created for the various issues
require_once( '../../../core.php' );
$t_login	= config_get( 'plugin_Reminder_reminder_login' );
$ok=auth_attempt_script_login( $t_login );
$t_core_path = config_get( 'core_path' );
require_once( $t_core_path.'bug_api.php' );
require_once( $t_core_path.'email_api.php' );
require_once( $t_core_path.'bugnote_api.php' );

$t_bug_table	= db_get_table( 'bug' );
$t_man_table	= db_get_table( 'project_user_list' );
$t_rem_days		= config_get( 'plugin_Reminder_reminder_days_treshold' );
$t_rem_status	= config_get( 'plugin_Reminder_reminder_bug_status' );
$t_rem_body		= config_get( 'plugin_Reminder_reminder_mail_subject' );
$t_rem_store	= config_get( 'plugin_Reminder_reminder_store_as_note' );
$t_rem_ignore	= config_get( 'plugin_Reminder_reminder_ignore_unset' );
$t_rem_ign_past	= config_get( 'plugin_Reminder_reminder_ignore_past' );
$t_rem_handler	= config_get( 'plugin_Reminder_reminder_handler' );
$t_rem_group1	= config_get( 'plugin_Reminder_reminder_group_issues' );
$t_rem_group2	= config_get( 'plugin_Reminder_reminder_group_project' );
$t_rem_manager	= config_get( 'plugin_Reminder_reminder_manager_overview' );
$t_rem_subject	= config_get( 'plugin_Reminder_reminder_group_subject' );
$t_rem_body1	= config_get( 'plugin_Reminder_reminder_group_body1' );
$t_rem_body2	= config_get( 'plugin_Reminder_reminder_group_body2' );
$t_rem_details	= config_get( 'plugin_Reminder_reminder_details' );
$t_rem_hours	= config_get('plugin_Reminder_reminder_hours');
if (ON != $t_rem_hours){
	$multiply=24;
} else{
	$multiply=1;
}
//
// access level for manager= 70
// this needs to be made flexible
// we will only produce overview for those projects that have a separate manager
//
$baseline	= time()+ ($t_rem_days*$multiply*60*60);
$basenow	= time();

if ( ON == $t_rem_handler ) {
	$query = "select id,handler_id,project_id,summary from $t_bug_table where status in (".implode(",", $t_rem_status).") and due_date<=$baseline and handler_id>0 ";
	if ( ON == $t_rem_ign_past ) {
			$query .=" and due_date>=$basenow" ;
	} else{
		if ( ON == $t_rem_ignore ) {
			$query .=" and due_date>1" ;
		}
	}
	$t_rem_include	= config_get('plugin_Reminder_reminder_include');
	$t_rem_projects	= "(";
	$t_rem_projects	.= config_get('plugin_Reminder_reminder_project_id');
	$t_rem_projects	.= ")";

	if (ON==$t_rem_include){
		if ($t_rem_projects <>"(0)") {
			$query .= " and $t_bug_table.project_id IN ". $t_rem_projects;
		}
	}
	
	if ( ON == $t_rem_group1 ) {
		$query .=" order by handler_id" ;
	} else {
		if ( ON == $t_rem_group2 ) {
			$query .=" order by $t_bug_table.project_id,handler_id" ;
		}
	}
	
	$results = db_query( $query );
	$resnum=db_num_rows($results);
	if ( OFF == $t_rem_group1 ) {
		if ($results) {
			while ($row1 = db_fetch_array($results)) {
				$id 		= $row1['id'];
				$handler	= $row1['handler_id'];
				$summary	= $row1['summary'];
				if ( OFF == $t_rem_details ) {
					$list = string_get_bug_view_url_with_fqdn( $id, $handler2 );			
				} else {
					$url = string_get_bug_view_url_with_fqdn( $id, $handler2 );
					$list = "  * $summary\n    $url\n";
				}
				$body  = $t_rem_body1;
				$body .= "\n\n";
				$body .= $list;
				$body .= "\n\n";
				$body .= $t_rem_body2;
				$result = email_group_reminder( $handler, $body );
				# Add reminder as bugnote if store reminders option is ON.
				if ( ON == $t_rem_store ) {
					$t_attr = '|'.$handler2.'|';
					bugnote_add( $id, $t_rem_body, 0, config_get( 'default_reminder_view_status' ) == VS_PRIVATE, REMINDER, $t_attr, NULL, FALSE );
				}
			}
		} 
	} else {
		if ($results){
			$start = true ;
			$list= "";
			// first group and store reminder per issue
			while ($row1 = db_fetch_array($results)) {
				$id 		= $row1['id'];
				$handler	= $row1['handler_id'];
				$project	= $row1['project_id'];
				$summary	= $row1['summary'];
				if ($start){
					$handler2 = $handler ;
					$start = false ;
				}
				if ($handler==$handler2){
					$list .="\n\n";
				if ( OFF == $t_rem_details ) {
					$list.= string_get_bug_view_url_with_fqdn( $id, $handler2 );			
				} else {
					$url = string_get_bug_view_url_with_fqdn( $id, $handler2 );
					$list.= "  * $summary\n    $url\n";
				}
					# Add reminder as bugnote if store reminders option is ON.
					if ( ON == $t_rem_store ) {
						$t_attr = '|'.$handler2.'|';
						bugnote_add( $id, $t_rem_body, 0, config_get( 'default_reminder_view_status' ) == VS_PRIVATE, REMINDER, $t_attr, NULL, FALSE );
					}
				} else {
					// now send the grouped email
					$body  = $t_rem_body1;
					$body .= "\n\n";
					$body .= $list;
					$body .= "\n\n";
					$body .= $t_rem_body2;
					$result = email_group_reminder( $handler2, $body);
					$handler2 = $handler ;
					$list ="\n\n";
				if ( OFF == $t_rem_details ) {
					$list = string_get_bug_view_url_with_fqdn( $id, $handler2 );			
				} else {
					$url = string_get_bug_view_url_with_fqdn( $id, $handler2 );
					$list = "  * $summary\n    $url\n";
				}
					# Add reminder as bugnote if store reminders option is ON.
					if ( ON == $t_rem_store ) {
						$t_attr = '|'.$handler2.'|';
						bugnote_add( $id, $t_rem_body, 0, config_get( 'default_reminder_view_status' ) == VS_PRIVATE, REMINDER, $t_attr, NULL, FALSE );
					}
				}
			}
			// handle last one
			if ($resnum>0){
				// now send the grouped email
				$body  = $t_rem_body1;
				$body .= "\n\n";
				$body .= $list;
				$body .= "\n\n";
				$body .= $t_rem_body2;
				$result = email_group_reminder( $handler2, $body);

			} 
			//
		}
	}
}

if ( ON == $t_rem_manager ) {
	// select relevant issues in combination with an assigned manager to the project
	$query  = "select id,handler_id,user_id,summary from $t_bug_table,$t_man_table where status in (".implode(",", $t_rem_status).") and due_date<=$baseline ";
	if ( ON == $t_rem_ign_past ) {
			$query .=" and due_date>=$basenow" ;
	} else{
		if ( ON == $t_rem_ignore ) {
			$query .=" and due_date>1" ;
		}
	}
	$t_rem_include	= config_get('plugin_Reminder_reminder_include');
	$t_rem_projects	= "(";
	$t_rem_projects	.= config_get('plugin_Reminder_reminder_project_id');
	$t_rem_projects	.= ")";
	if (ON==$t_rem_include){
		if ($t_rem_projects <>"0") {
			$query .= " and $t_bug_table.project_id IN ". $t_rem_projects;
		}
	} else {
		$query .= " and $t_bug_table.project_id NOT IN ".$t_rem_projects;
	}

	$query .=" and $t_bug_table.project_id=$t_man_table.project_id and $t_man_table.access_level=70" ;
	$query .=" order by $t_man_table.project_id,$t_man_table.user_id" ;

	$results = db_query( $query );
	$resnum=db_num_rows($results);
	if ($results){
		$start = true ;
		$list= "";
		// first group and store reminder per issue
		while ($row1 = db_fetch_array($results)) {
			$id 		= $row1['id'];
			$handler	= $row1['handler_id'];
			$manager	= $row1['user_id'];
			$summary= $row1['summary'];
			if ($start){
				$man2 = $manager ;
				$start = false ;
			}
			if ($manager==$man2){
				$list .=" \n\n";
				if ( OFF == $t_rem_details ) {
					$list.= string_get_bug_view_url_with_fqdn( $id, $handler2 );			
				} else {
					$url = string_get_bug_view_url_with_fqdn( $id, $handler2 );
					$list.= "  * $summary\n    $url\n";
				}
			} else {
				// now send the grouped email
				$body  = $t_rem_body1. " \n\n";
				$body .= $list. " \n\n";
				$body .= $t_rem_body2;
				$result = email_group_reminder( $man2, $body);
				$man2 = $manager ;
				if ( OFF == $t_rem_details ) {
					$list = string_get_bug_view_url_with_fqdn( $id, $man2 );			
				} else {
					$url = string_get_bug_view_url_with_fqdn( $id, $man2 );
					$list = "  * $summary\n    $url\n";
				}				$list .= " \n\n";
			}
		}
		// handle last one
		if ($resnum>0){
			// now send the grouped email
			$body  = $t_rem_body1. " \n\n";
			$body .= $list. " \n\n";
			$body .= $t_rem_body2;
			$result = email_group_reminder( $man2, $body);

		} 
		//
	} 
}


# Send Grouped reminder
function email_group_reminder( $p_user_id, $issues ) {
	$t_username = user_get_field( $p_user_id, 'username' );
	$t_email = user_get_email( $p_user_id );
	$t_subject = config_get( 'plugin_Reminder_reminder_group_subject' );
	$t_message = $issues ;
	if( !is_blank( $t_email ) ) {
		email_store( $t_email, $t_subject, $t_message );
		if( OFF == config_get( 'email_send_using_cronjob' ) ) {
			email_send_all();
		}
	}
}
