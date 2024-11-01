<tr valign="top" id="service_options">
	<th scope="row" class="titledesc"><?php _e( 'Services', 'RapidPlugin-DayAndRoss' ); ?></th>
	<td class="forminp">
		<table class="RapidPlugin_DayAndRosss widefat">
			<thead>
				<th class="sort">&nbsp;</th>
				<th><?php _e( 'Service Code', 'RapidPlugin-DayAndRoss' ); ?></th>
				<th><?php _e( 'Name', 'RapidPlugin-DayAndRoss' ); ?></th>
				<th><?php _e( 'Enabled', 'RapidPlugin-DayAndRoss' ); ?></th>
				<th><?php _e( 'Delay (in days)', 'RapidPlugin-DayAndRoss' ); ?></th>
				<th><?php echo sprintf( __( 'Price Adjustment (%s)', 'RapidPlugin-DayAndRoss' ), get_woocommerce_currency_symbol() ); ?></th>
				<th><?php _e( 'Price Adjustment (%)', 'RapidPlugin-DayAndRoss' ); ?></th>
			</thead>
			<tbody>
				<?php
					$sort = 0;
					$this->ordered_services = [];

					foreach ( $this->services as $code => $name ) {

						if ( isset( $this->custom_services[ $code ]['order'] ) ) {
							$sort = $this->custom_services[ $code ]['order'];
						}

						while ( isset( $this->ordered_services[ $sort ] ) )
							$sort++;

						$this->ordered_services[ $sort ] = array( $code, $name );

						$sort++;
					}

					ksort( $this->ordered_services );

					foreach ( $this->ordered_services as $value ) {
						$code = $value[0];
						$name = $value[1];
						?>
						<tr>
							<td class="sort"><input type="hidden" class="order" name="RapidPlugin_DayAndRoss[<?php echo esc_attr($code); ?>][order]" value="<?php if(isset( $this->custom_services[ $code ]['order'] )) _e($this->custom_services[ $code ]['order']); ?>" /></td>
							<td><strong><?php echo esc_attr($code); ?></strong></td>
							<td><input type="text" name="RapidPlugin_DayAndRoss[<?php echo esc_attr($code); ?>][name]" placeholder="<?php echo esc_attr($name); ?>" value="<?php if(isset( $this->custom_services[ $code ]['name'] )) _e($this->custom_services[ $code ]['name']); ?>" size="65" /></td>
							<td><input type="checkbox" name="RapidPlugin_DayAndRoss[<?php echo esc_attr($code); ?>][enabled]" <?php checked( ( ! isset( $this->custom_services[ $code ]['enabled'] ) || ! empty( $this->custom_services[ $code ]['enabled'] ) ), true ); ?> /></td>
							<td><input type="text" name="RapidPlugin_DayAndRoss[<?php echo esc_attr($code); ?>][delay]" placeholder="0" value="<?php echo isset( $this->custom_services[ $code ]['delay'] ) ? intval($this->custom_services[ $code ]['delay']) : ''; ?>" size="4" /></td>
                            <td><select style="margin-right: 5px;height: 10px;" name="RapidPlugin_DayAndRoss[<?php echo esc_attr($code); ?>][adjustment_type]"><option></option><option value="1" <?php echo isset( $this->custom_services[ $code ]['adjustment_type'] ) && $this->custom_services[ $code ]['adjustment_type'] == '1' ? 'selected' : ''; ?>>+</option><option value="2" <?php echo isset( $this->custom_services[ $code ]['adjustment_type'] ) && $this->custom_services[ $code ]['adjustment_type'] == '2' ? 'selected' : ''; ?>>-</option></select><input type="text" name="RapidPlugin_DayAndRoss[<?php echo esc_attr($code); ?>][adjustment]" placeholder="N/A" value="<?php echo isset( $this->custom_services[ $code ]['adjustment'] ) ? intval($this->custom_services[ $code ]['adjustment']) : ''; ?>" size="4" /></td>
							<td><select style="margin-right: 5px;height: 10px;" name="RapidPlugin_DayAndRoss[<?php echo esc_attr($code); ?>][adjustment_type2]"><option></option><option value="1" <?php echo isset( $this->custom_services[ $code ]['adjustment_type2'] ) && $this->custom_services[ $code ]['adjustment_type2'] == '1' ? 'selected' : ''; ?>>+</option><option value="2" <?php echo isset( $this->custom_services[ $code ]['adjustment_type2'] ) && $this->custom_services[ $code ]['adjustment_type2'] == '2' ? 'selected' : ''; ?>>-</option></select><input type="text" name="RapidPlugin_DayAndRoss[<?php echo esc_attr($code); ?>][adjustment_percent]" placeholder="N/A" value="<?php echo isset( $this->custom_services[ $code ]['adjustment_percent'] ) ? intval($this->custom_services[ $code ]['adjustment_percent']) : ''; ?>" size="4" /></td>
						</tr>
						<?php
					}
				?>
			</tbody>
		</table>
	</td>
</tr>

<style>
    .RapidPlugin_DayAndRosss td {
        vertical-align: middle;
        padding: 4px 7px;
        text-align: center;
    }
    .woocommerce table.form-table table.widefat th {
        text-align: center;
    }
    .RapidPlugin_DayAndRosss th {
        padding: 9px 7px;
    }
    .RapidPlugin_DayAndRosss th.sort {
        width: 16px;
        padding: 0 16px;
    }
    .RapidPlugin_DayAndRosss td.sort {
        cursor: move;
        width: 16px;
        padding: 0 16px;
        cursor: move;
        background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAAHUlEQVQYV2O8f//+fwY8gJGgAny6QXKETRgEVgAAXxAVsa5Xr3QAAAAASUVORK5CYII=) no-repeat center;
    }
</style>
<script type="text/javascript">

    jQuery(window).load(function(){
        jQuery('.RapidPlugin_DayAndRosss tbody').sortable({
            items:'tr',
            cursor:'move',
            axis:'y',
            handle: '.sort',
            scrollSensitivity:40,
            forcePlaceholderSize: true,
            helper: 'clone',
            opacity: 0.65,
            placeholder: 'wc-metabox-sortable-placeholder',
            start:function(event,ui){
                ui.item.css('baclbsround-color','#f6f6f6');
            },
            stop:function(event,ui){
                ui.item.removeAttr('style');
                RapidPlugin_DayAndRosss_row_indexes();
            }
        });

        function RapidPlugin_DayAndRosss_row_indexes() {
            jQuery('.RapidPlugin_DayAndRosss tbody tr').each(function(index, el){
                jQuery('input.order', el).val( parseInt( jQuery(el).index('.RapidPlugin_DayAndRosss tr') ) );
            });
        }
    });

</script>