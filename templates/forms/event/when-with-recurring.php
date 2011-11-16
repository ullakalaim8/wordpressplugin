<?php
global $EM_Event, $localised_date_formats;
$days_names = array (1 => __ ( 'Mon' ), 2 => __ ( 'Tue' ), 3 => __ ( 'Wed' ), 4 => __ ( 'Thu' ), 5 => __ ( 'Fri' ), 6 => __ ( 'Sat' ), 0 => __ ( 'Sun' ) );
$locale_code = substr ( get_locale (), 0, 2 );
$localised_date_format = $localised_date_formats[$locale_code];
$hours_locale_regexp = "H:i";
// Setting 12 hours format for those countries using it
if (preg_match ( "/en|sk|zh|us|uk/", $locale_code ))
	$hours_locale_regexp = "h:iA";
?>
<?php if( is_admin() ): ?><input type="hidden" name="_emnonce" value="<?php echo wp_create_nonce('edit_event'); ?>" /><?php endif; ?>
<!-- START recurrence postbox -->
<div id="em-form-recurrence" class="event-form-recurrence event-form-when">
	<p>
		<span class="em-recurring-text"><?php _e ( 'Recurrences span from ', 'dbem' ); ?></span>
		<span class="em-event-text"><?php _e ( 'From ', 'dbem' ); ?></span>				
		<input id="em-date-start-loc" type="text" />
		<input id="em-date-start" type="hidden" name="event_start_date" value="<?php echo $EM_Event->event_start_date ?>" />
		<span class="em-recurring-text"><?php _e('to','dbem'); ?></span>
		<span class="em-event-text"><?php _e('to','dbem'); ?></span>
		<input id="em-date-end-loc" type="text" />
		<input id="em-date-end" type="hidden" name="event_end_date" value="<?php echo $EM_Event->event_end_date ?>" />
	</p>
	<p>
		<?php _e('Events start from','dbem'); ?>
		<input id="start-time" type="text" size="8" maxlength="8" name="event_start_time" value="<?php echo date( $hours_locale_regexp, $EM_Event->start ); ?>" />
		<?php _e('and ends at','dbem'); ?>
		<input id="end-time" type="text" size="8" maxlength="8" name="event_end_time" value="<?php echo date( $hours_locale_regexp, $EM_Event->end ); ?>" />
	</p>
	<div class="em-recurring-text">
		<p>
			<?php _e ( 'This event repeats', 'dbem' ); ?> 
			<select id="recurrence-frequency" name="recurrence_freq">
				<?php
					$freq_options = array ("daily" => __ ( 'Daily', 'dbem' ), "weekly" => __ ( 'Weekly', 'dbem' ), "monthly" => __ ( 'Monthly', 'dbem' ) );
					em_option_items ( $freq_options, $EM_Event->recurrence_freq ); 
				?>
			</select>
			<?php _e ( 'every', 'dbem' )?>
			<input id="recurrence-interval" name='recurrence_interval' size='2' value='<?php echo $EM_Event->interval ; ?>' />
			<span class='interval-desc' id="interval-daily-singular">
			<?php _e ( 'day', 'dbem' )?>
			</span> <span class='interval-desc' id="interval-daily-plural">
			<?php _e ( 'days', 'dbem' ) ?>
			</span> <span class='interval-desc' id="interval-weekly-singular">
			<?php _e ( 'week on', 'dbem'); ?>
			</span> <span class='interval-desc' id="interval-weekly-plural">
			<?php _e ( 'weeks on', 'dbem'); ?>
			</span> <span class='interval-desc' id="interval-monthly-singular">
			<?php _e ( 'month on the', 'dbem' )?>
			</span> <span class='interval-desc' id="interval-monthly-plural">
			<?php _e ( 'months on the', 'dbem' )?>
			</span>
		</p>
		<p class="alternate-selector" id="weekly-selector">
			<?php
				$saved_bydays = ($EM_Event->is_recurring()) ? explode ( ",", $EM_Event->recurrence_byday ) : array(); 
				em_checkbox_items ( 'recurrence_bydays[]', $days_names, $saved_bydays ); 
			?>
		</p>
		<p class="alternate-selector" id="monthly-selector" style="display:inline;">
			<select id="monthly-modifier" name="recurrence_byweekno">
				<?php
					$weekno_options = array ("1" => __ ( 'first', 'dbem' ), '2' => __ ( 'second', 'dbem' ), '3' => __ ( 'third', 'dbem' ), '4' => __ ( 'fourth', 'dbem' ), '-1' => __ ( 'last', 'dbem' ) ); 
					em_option_items ( $weekno_options, $EM_Event->recurrence_byweekno  ); 
				?>
			</select>
			<select id="recurrence-weekday" name="recurrence_byday">
				<?php em_option_items ( $days_names, $EM_Event->recurrence_byday  ); ?>
			</select>
			<?php _e('of each month','dbem'); ?>
		</p>
		<p>
			<?php _e('Each event lasts','dbem'); ?>
			<input id="end-days" type="text" size="8" maxlength="8" name="recurrence_days" value="<?php echo $EM_Event->recurrence_days; ?>" />
			<?php _e('day(s)','dbem'); ?>
		</p>
		<em><?php _e( 'For a recurring event, a one day event will be created on each recurring date within this date range.', 'dbem' ); ?></em><br/>
	</div>
	<script type="text/javascript">
		jQuery(document).ready( function($) {
			//Recurrence Warnings
			$('#event_form').submit( function(event){
				confirmation = confirm(EM.event_reschedule_warning);
				if( confirmation == false ){
					event.preventDefault();
				}
			});
		});		
	</script>
</div>