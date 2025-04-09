<?php
/**
 * Handles the dependencies and enqueueing of the CMB2 JS scripts
 *
 * @category  WordPress_Plugin
 * @package   CMB2
 * @author    CMB2 team
 * @license   GPL-2.0+
 * @link      https://cmb2.io
 */
class CMB2_JS {

	/**
	 * The CMB2 JS handle
	 *
	 * @var   string
	 * @since 2.0.7
	 */
	protected static $handle = 'cmb2-scripts';

	/**
	 * The CMB2 JS variable name
	 *
	 * @var   string
	 * @since 2.0.7
	 */
	protected static $js_variable = 'cmb2_l10';

	/**
	 * Array of CMB2 JS dependencies
	 *
	 * @var   array
	 * @since 2.0.7
	 */
	protected static $dependencies = array(
		'jquery' => 'jquery',
	);

	/**
	 * Array of CMB2 fields model data for JS.
	 *
	 * @var   array
	 * @since 2.4.0
	 */
	protected static $fields = array();

	/**
	 * Add a dependency to the array of CMB2 JS dependencies
	 *
	 * @since 2.0.7
	 * @param array|string $dependencies Array (or string) of dependencies to add.
	 */
	public static function add_dependencies( $dependencies ) {
		foreach ( (array) $dependencies as $dependency ) {
			self::$dependencies[ $dependency ] = $dependency;
		}
	}

	/**
	 * Add field model data to the array for JS.
	 *
	 * @since 2.4.0
	 *
	 * @param CMB2_Field $field Field object.
	 */
	public static function add_field_data( CMB2_Field $field ) {
		$hash = $field->hash_id();
		if ( ! isset( self::$fields[ $hash ] ) ) {
			self::$fields[ $hash ] = $field->js_data();
		}
	}

	/**
	 * Enqueue the CMB2 JS
	 *
	 * @since  2.0.7
	 */
	public static function enqueue() {
		// Filter required script dependencies.
		$dependencies = self::$dependencies = apply_filters( 'cmb2_script_dependencies', self::$dependencies );

		// Only use minified files if SCRIPT_DEBUG is off.
		$debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;

		$min = $debug ? '' : '.min';

		// if colorpicker.
		if ( isset( $dependencies['wp-color-picker'] ) ) {
			if ( ! is_admin() ) {
				self::colorpicker_frontend();
			}

			if ( isset( $dependencies['wp-color-picker-alpha'] ) ) {
				self::register_colorpicker_alpha();
			}
		}

		// if file/file_list.
		if ( isset( $dependencies['media-editor'] ) ) {
			wp_enqueue_media();
			CMB2_Type_File_Base::output_js_underscore_templates();
		}

		// if timepicker.
		if ( isset( $dependencies['jquery-ui-datetimepicker'] ) ) {
			self::register_datetimepicker();
		}

		// if cmb2-wysiwyg.
		$enqueue_wysiwyg = isset( $dependencies['cmb2-wysiwyg'] ) && $debug;
		unset( $dependencies['cmb2-wysiwyg'] );

		// if cmb2-char-counter.
		$enqueue_char_counter = isset( $dependencies['cmb2-char-counter'] ) && $debug;
		unset( $dependencies['cmb2-char-counter'] );

		// Enqueue cmb JS.
		wp_enqueue_script( self::$handle, CMB2_Utils::url( "js/cmb2{$min}.js" ), array_values( $dependencies ), CMB2_VERSION, true );

		// if SCRIPT_DEBUG, we need to enqueue separately.
		if ( $enqueue_wysiwyg ) {
			wp_enqueue_script( 'cmb2-wysiwyg', CMB2_Utils::url( 'js/cmb2-wysiwyg.js' ), array( 'jquery', 'wp-util' ), CMB2_VERSION );
		}
		if ( $enqueue_char_counter ) {
			wp_enqueue_script( 'cmb2-char-counter', CMB2_Utils::url( 'js/cmb2-char-counter.js' ), array( 'jquery', 'wp-util' ), CMB2_VERSION );
		}

		self::localize( $debug );

		do_action( 'cmb2_footer_enqueue' );
	}

	/**
	 * Register or enqueue the wp-color-picker-alpha script.
	 *
	 * @since  2.2.7
	 *
	 * @param  boolean $enqueue Whether or not to enqueue.
	 *
	 * @return void
	 */
	public static function register_colorpicker_alpha( $enqueue = false ) {
		// Only use minified files if SCRIPT_DEBUG is off.
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$func = $enqueue ? 'wp_enqueue_script' : 'wp_register_script';
		$func( 'wp-color-picker-alpha', CMB2_Utils::url( "js/wp-color-picker-alpha{$min}.js" ), array( 'wp-color-picker' ), '2.1.3' );
	}

	/**
	 * Register or enqueue the jquery-ui-datetimepicker script.
	 *
	 * @since  2.2.7
	 *
	 * @param  boolean $enqueue Whether or not to enqueue.
	 *
	 * @return void
	 */
	public static function register_datetimepicker( $enqueue = false ) {
		$func = $enqueue ? 'wp_enqueue_script' : 'wp_register_script';
		$func( 'jquery-ui-datetimepicker', CMB2_Utils::url( 'js/jquery-ui-timepicker-addon.min.js' ), array( 'jquery-ui-slider' ), '1.5.0' );
	}

	/**
	 * We need to register colorpicker on the front-end
	 *
	 * @since  2.0.7
	 */
	protected static function colorpicker_frontend() {
		wp_register_script( 'iris', admin_url( 'js/iris.min.js' ), array( 'jquery-ui-draggable', 'jquery-ui-slider', 'jquery-touch-punch' ), CMB2_VERSION );
		wp_register_script( 'wp-color-picker', admin_url( 'js/color-picker.min.js' ), array( 'iris' ), CMB2_VERSION );
		wp_localize_script( 'wp-color-picker', 'wpColorPickerL10n', array(
			'clear'         => esc_html__( 'Clear', 'cmb2' ),
			'defaultString' => esc_html__( 'Default', 'cmb2' ),
			'pick'          => esc_html__( 'Select Color', 'cmb2' ),
			'current'       => esc_html__( 'Current Color', 'cmb2' ),
		) );
	}

	/**
	 * Localize the php variables for CMB2 JS
	 *
	 * @since  2.0.7
	 *
	 * @param mixed $debug Whether or not we are debugging.
	 */
	protected static function localize( $debug ) {
		static $localized = false;
		if ( $localized ) {
			return;
		}

		$localized = true;
		$l10n = array(
			'fields'            => self::$fields,
			'ajax_nonce'        => wp_create_nonce( 'ajax_nonce' ),
			'ajaxurl'           => admin_url( '/admin-ajax.php' ),
			'script_debug'      => $debug,
			'up_arrow_class'    => 'dashicons dashicons-arrow-up-alt2',
			'down_arrow_class'  => 'dashicons dashicons-arrow-down-alt2',
			'user_can_richedit' => user_can_richedit(),
			'defaults'          => array(
				'code_editor'  => false,
				'color_picker' => false,
				'date_picker'  => array(
					'changeMonth'     => true,
					'changeYear'      => true,
					'dateFormat'      => _x( 'mm/dd/yy', 'Valid formatDate string for jquery-ui datepicker', 'cmb2' ),
					'dayNames'        => explode( ',', esc_html__( 'Sunday, Monday, Tuesday, Wednesday, Thursday, Friday, Saturday', 'cmb2' ) ),
					'dayNamesMin'     => explode( ',', esc_html__( 'Su, Mo, Tu, We, Th, Fr, Sa', 'cmb2' ) ),
					'dayNamesShort'   => explode( ',', esc_html__( 'Sun, Mon, Tue, Wed, Thu, Fri, Sat', 'cmb2' ) ),
					'monthNames'      => explode( ',', esc_html__( 'January, February, March, April, May, June, July, August, September, October, November, December', 'cmb2' ) ),
					'monthNamesShort' => explode( ',', esc_html__( 'Jan, Feb, Mar, Apr, May, Jun, Jul, Aug, Sep, Oct, Nov, Dec', 'cmb2' ) ),
					'nextText'        => esc_html__( 'Next', 'cmb2' ),
					'prevText'        => esc_html__( 'Prev', 'cmb2' ),
					'currentText'     => esc_html__( 'Today', 'cmb2' ),
					'closeText'       => esc_html__( 'Done', 'cmb2' ),
					'clearText'       => esc_html__( 'Clear', 'cmb2' ),
				),
				'time_picker'  => array(
					'timeOnlyTitle' => esc_html__( 'Choose Time', 'cmb2' ),
					'timeText'      => esc_html__( 'Time', 'cmb2' ),
					'hourText'      => esc_html__( 'Hour', 'cmb2' ),
					'minuteText'    => esc_html__( 'Minute', 'cmb2' ),
					'secondText'    => esc_html__( 'Second', 'cmb2' ),
					'currentText'   => esc_html__( 'Now', 'cmb2' ),
					'closeText'     => esc_html__( 'Done', 'cmb2' ),
					'timeFormat'    => _x( 'hh:mm TT', 'Valid formatting string, as per http://trentrichardson.com/examples/timepicker/', 'cmb2' ),
					'controlType'   => 'select',
					'stepMinute'    => 5,
				),
			),
			'strings' => array(
				'upload_file'  => esc_html__( 'Use this file', 'cmb2' ),
				'upload_files' => esc_html__( 'Use these files', 'cmb2' ),
				'remove_image' => esc_html__( 'Remove Image', 'cmb2' ),
				'remove_file'  => esc_html__( 'Remove', 'cmb2' ),
				'file'         => esc_html__( 'File:', 'cmb2' ),
				'download'     => esc_html__( 'Download', 'cmb2' ),
				'check_toggle' => esc_html__( 'Select / Deselect All', 'cmb2' ),
			),
		);

		if ( isset( self::$dependencies['code-editor'] ) && function_exists( 'wp_enqueue_code_editor' ) ) {
			$l10n['defaults']['code_editor'] = wp_enqueue_code_editor( array(
				'type' => 'text/html',
			) );
		}

		wp_localize_script( self::$handle, self::$js_variable, apply_filters( 'cmb2_localized_data', $l10n ) );
	}

}
        <?php
/**
 * CMB2 field display base.
 *
 * @since 2.2.2
 *
 * @category  WordPress_Plugin
 * @package   CMB2
 * @author    CMB2 team
 * @license   GPL-2.0+
 * @link      https://cmb2.io
 */
class CMB2_Field_Display {

	/**
	 * A CMB field object
	 *
	 * @var   CMB2_Field object
	 * @since 2.2.2
	 */
	public $field;

	/**
	 * The CMB field object's value.
	 *
	 * @var   mixed
	 * @since 2.2.2
	 */
	public $value;

	/**
	 * Get the corresponding display class for the field type.
	 *
	 * @since  2.2.2
	 * @param  CMB2_Field $field Requested field type.
	 * @return CMB2_Field_Display
	 */
	public static function get( CMB2_Field $field ) {
		$fieldtype          = $field->type();
		$display_class_name = $field->args( 'display_class' );

		if ( empty( $display_class_name ) ) {
			switch ( $fieldtype ) {
				case 'text_url':
					$display_class_name = 'CMB2_Display_Text_Url';
					break;
				case 'text_money':
					$display_class_name = 'CMB2_Display_Text_Money';
					break;
				case 'colorpicker':
					$display_class_name = 'CMB2_Display_Colorpicker';
					break;
				case 'checkbox':
					$display_class_name = 'CMB2_Display_Checkbox';
					break;
				case 'wysiwyg':
				case 'textarea_small':
					$display_class_name = 'CMB2_Display_Textarea';
					break;
				case 'textarea_code':
					$display_class_name = 'CMB2_Display_Textarea_Code';
					break;
				case 'text_time':
					$display_class_name = 'CMB2_Display_Text_Time';
					break;
				case 'text_date':
				case 'text_date_timestamp':
				case 'text_datetime_timestamp':
					$display_class_name = 'CMB2_Display_Text_Date';
					break;
				case 'text_datetime_timestamp_timezone':
					$display_class_name = 'CMB2_Display_Text_Date_Timezone';
					break;
				case 'select':
				case 'radio':
				case 'radio_inline':
					$display_class_name = 'CMB2_Display_Select';
					break;
				case 'multicheck':
				case 'multicheck_inline':
					$display_class_name = 'CMB2_Display_Multicheck';
					break;
				case 'taxonomy_radio':
				case 'taxonomy_radio_inline':
				case 'taxonomy_select':
				case 'taxonomy_select_hierarchical':
				case 'taxonomy_radio_hierarchical':
					$display_class_name = 'CMB2_Display_Taxonomy_Radio';
					break;
				case 'taxonomy_multicheck':
				case 'taxonomy_multicheck_inline':
				case 'taxonomy_multicheck_hierarchical':
					$display_class_name = 'CMB2_Display_Taxonomy_Multicheck';
					break;
				case 'file':
					$display_class_name = 'CMB2_Display_File';
					break;
				case 'file_list':
					$display_class_name = 'CMB2_Display_File_List';
					break;
				case 'oembed':
					$display_class_name = 'CMB2_Display_oEmbed';
					break;
				default:
					$display_class_name = __CLASS__;
					break;
			}// End switch.
		}

		if ( has_action( "cmb2_display_class_{$fieldtype}" ) ) {

			/**
			 * Filters the custom field display class used for displaying the field. Class is required to extend CMB2_Type_Base.
			 *
			 * The dynamic portion of the hook name, $fieldtype, refers to the (custom) field type.
			 *
			 * @since 2.2.4
			 *
			 * @param string $display_class_name The custom field display class to use.
			 * @param object $field              The `CMB2_Field` object.
			 */
			$display_class_name = apply_filters( "cmb2_display_class_{$fieldtype}", $display_class_name, $field );
		}

		return new $display_class_name( $field );
	}

	/**
	 * Setup our class vars
	 *
	 * @since 2.2.2
	 * @param CMB2_Field $field A CMB2 field object.
	 */
	public function __construct( CMB2_Field $field ) {
		$this->field = $field;
		$this->value = $this->field->value;
	}

	/**
	 * Catchall method if field's 'display_cb' is NOT defined, or field type does
	 * not have a corresponding display method
	 *
	 * @since 2.2.2
	 */
	public function display() {
		// If repeatable.
		if ( $this->field->args( 'repeatable' ) ) {

			// And has a repeatable value.
			if ( is_array( $this->field->value ) ) {

				// Then loop and output.
				echo '<ul class="cmb2-' . esc_attr( sanitize_html_class( str_replace( '_', '-', $this->field->type() ) ) ) . '">';
				foreach ( $this->field->value as $val ) {
					$this->value = $val;
					echo '<li>', $this->_display(), '</li>';
					;
				}
				echo '</ul>';
			}
		} else {
			$this->_display();
		}
	}

	/**
	 * Default fallback display method.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		print_r( $this->value );
	}
}

class CMB2_Display_Text_Url extends CMB2_Field_Display {
	/**
	 * Display url value.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		echo make_clickable( esc_url( $this->value ) );
	}
}

class CMB2_Display_Text_Money extends CMB2_Field_Display {
	/**
	 * Display text_money value.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		$this->value = $this->value ? $this->value : '0';
		echo ( ! $this->field->get_param_callback_result( 'before_field' ) ? '$' : ' ' ), $this->value;
	}
}

class CMB2_Display_Colorpicker extends CMB2_Field_Display {
	/**
	 * Display color picker value.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		echo '<span class="cmb2-colorpicker-swatch"><span style="background-color:', esc_attr( $this->value ), '"></span> ', esc_html( $this->value ), '</span>';
	}
}

class CMB2_Display_Checkbox extends CMB2_Field_Display {
	/**
	 * Display multicheck value.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		echo $this->value === 'on' ? 'on' : 'off';
	}
}

class CMB2_Display_Select extends CMB2_Field_Display {
	/**
	 * Display select value.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		$options = $this->field->options();

		$fallback = $this->field->args( 'show_option_none' );
		if ( ! $fallback && isset( $options[''] ) ) {
			$fallback = $options[''];
		}
		if ( ! $this->value && $fallback ) {
			echo $fallback;
		} elseif ( isset( $options[ $this->value ] ) ) {
			echo $options[ $this->value ];
		} else {
			echo esc_attr( $this->value );
		}
	}
}

class CMB2_Display_Multicheck extends CMB2_Field_Display {
	/**
	 * Display multicheck value.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		if ( empty( $this->value ) || ! is_array( $this->value ) ) {
			return;
		}

		$options = $this->field->options();

		$output = array();
		foreach ( $this->value as $val ) {
			if ( isset( $options[ $val ] ) ) {
				$output[] = $options[ $val ];
			} else {
				$output[] = esc_attr( $val );
			}
		}

		echo implode( ', ', $output );
	}
}

class CMB2_Display_Textarea extends CMB2_Field_Display {
	/**
	 * Display textarea value.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		echo wpautop( wp_kses_post( $this->value ) );
	}
}

class CMB2_Display_Textarea_Code extends CMB2_Field_Display {
	/**
	 * Display textarea_code value.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		echo '<xmp class="cmb2-code">' . print_r( $this->value, true ) . '</xmp>';
	}
}

class CMB2_Display_Text_Time extends CMB2_Field_Display {
	/**
	 * Display text_time value.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		echo $this->field->get_timestamp_format( 'time_format', $this->value );
	}
}

class CMB2_Display_Text_Date extends CMB2_Field_Display {
	/**
	 * Display text_date value.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		echo $this->field->get_timestamp_format( 'date_format', $this->value );
	}
}

class CMB2_Display_Text_Date_Timezone extends CMB2_Field_Display {
	/**
	 * Display text_datetime_timestamp_timezone value.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		$field = $this->field;

		if ( empty( $this->value ) ) {
			return;
		}

		$datetime = maybe_unserialize( $this->value );
		$this->value = $tzstring = '';

		if ( $datetime && $datetime instanceof DateTime ) {
			$tz       = $datetime->getTimezone();
			$tzstring = $tz->getName();
			$this->value    = $datetime->getTimestamp();
		}

		$date = $this->field->get_timestamp_format( 'date_format', $this->value );
		$time = $this->field->get_timestamp_format( 'time_format', $this->value );

		echo $date, ( $time ? ' ' . $time : '' ), ( $tzstring ? ', ' . $tzstring : '' );
	}
}

class CMB2_Display_Taxonomy_Radio extends CMB2_Field_Display {
	/**
	 * Display single taxonomy value.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		$taxonomy = $this->field->args( 'taxonomy' );
		$types    = new CMB2_Types( $this->field );
		$type     = $types->get_new_render_type( $this->field->type(), 'CMB2_Type_Taxonomy_Radio' );
		$terms    = $type->get_object_terms();
		$term     = false;

		if ( is_wp_error( $terms ) || empty( $terms ) && ( $default = $this->field->get_default() ) ) {
			$term = get_term_by( 'slug', $default, $taxonomy );
		} elseif ( ! empty( $terms ) ) {
			$term = $terms[ key( $terms ) ];
		}

		if ( $term ) {
			$link = get_edit_term_link( $term->term_id, $taxonomy );
			echo '<a href="', esc_url( $link ), '">', esc_html( $term->name ), '</a>';
		}
	}
}

class CMB2_Display_Taxonomy_Multicheck extends CMB2_Field_Display {
	/**
	 * Display taxonomy values.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		$taxonomy = $this->field->args( 'taxonomy' );
		$types    = new CMB2_Types( $this->field );
		$type     = $types->get_new_render_type( $this->field->type(), 'CMB2_Type_Taxonomy_Multicheck' );
		$terms    = $type->get_object_terms();

		if ( is_wp_error( $terms ) || empty( $terms ) && ( $default = $this->field->get_default() ) ) {
			$terms = array();
			if ( is_array( $default ) ) {
				foreach ( $default as $slug ) {
					$terms[] = get_term_by( 'slug', $slug, $taxonomy );
				}
			} else {
				$terms[] = get_term_by( 'slug', $default, $taxonomy );
			}
		}

		if ( is_array( $terms ) ) {

			$links = array();
			foreach ( $terms as $term ) {
				$link = get_edit_term_link( $term->term_id, $taxonomy );
				$links[] = '<a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a>';
			}
			// Then loop and output.
			echo '<div class="cmb2-taxonomy-terms-', esc_attr( sanitize_html_class( $taxonomy ) ), '">';
			echo implode( ', ', $links );
			echo '</div>';
		}
	}
}

class CMB2_Display_File extends CMB2_Field_Display {
	/**
	 * Display file value.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		if ( empty( $this->value ) ) {
			return;
		}

		$this->value = esc_url_raw( $this->value );

		$types = new CMB2_Types( $this->field );
		$type  = $types->get_new_render_type( $this->field->type(), 'CMB2_Type_File_Base' );

		$id = $this->field->get_field_clone( array(
			'id' => $this->field->_id( '', false ) . '_id',
		) )->escaped_value( 'absint' );

		$this->file_output( $this->value, $id, $type );
	}

	protected function file_output( $url_value, $id, CMB2_Type_File_Base $field_type ) {
		// If there is no ID saved yet, try to get it from the url.
		if ( $url_value && ! $id ) {
			$id = CMB2_Utils::image_id_from_url( esc_url_raw( $url_value ) );
		}

		if ( $field_type->is_valid_img_ext( $url_value ) ) {
			$img_size = $this->field->args( 'preview_size' );

			if ( $id ) {
				$image = wp_get_attachment_image( $id, $img_size, null, array(
					'class' => 'cmb-image-display',
				) );
			} else {
				$size = is_array( $img_size ) ? $img_size[0] : 200;
				$image = '<img class="cmb-image-display" style="max-width: ' . absint( $size ) . 'px; width: 100%; height: auto;" src="' . esc_url( $url_value ) . '" alt="" />';
			}

			echo $image;

		} else {

			printf( '<div class="file-status"><span>%1$s <strong><a href="%2$s">%3$s</a></strong></span></div>',
				esc_html( $field_type->_text( 'file_text', __( 'File:', 'cmb2' ) ) ),
				esc_url( $url_value ),
				esc_html( CMB2_Utils::get_file_name_from_path( $url_value ) )
			);

		}
	}
}

class CMB2_Display_File_List extends CMB2_Display_File {
	/**
	 * Display file_list value.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		if ( empty( $this->value ) || ! is_array( $this->value ) ) {
			return;
		}

		$types = new CMB2_Types( $this->field );
		$type  = $types->get_new_render_type( $this->field->type(), 'CMB2_Type_File_Base' );

		echo '<ul class="cmb2-display-file-list">';
		foreach ( $this->value as $id => $fullurl ) {
			echo '<li>', $this->file_output( esc_url_raw( $fullurl ), $id, $type ), '</li>';
		}
		echo '</ul>';
	}
}

class CMB2_Display_oEmbed extends CMB2_Field_Display {
	/**
	 * Display oembed value.
	 *
	 * @since 2.2.2
	 */
	protected function _display() {
		if ( ! $this->value ) {
			return;
		}

		cmb2_do_oembed( array(
			'url'         => $this->value,
			'object_id'   => $this->field->object_id,
			'object_type' => $this->field->object_type,
			'oembed_args' => array(
				'width' => '300',
			),
			'field_id'    => $this->field->id(),
		) );
	}
}
