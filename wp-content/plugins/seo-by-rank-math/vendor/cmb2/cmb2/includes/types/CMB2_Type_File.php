<?php
/**
 * CMB base field type
 *
 * @since  2.2.2
 *
 * @category  WordPress_Plugin
 * @package   CMB2
 * @author    CMB2 team
 * @license   GPL-2.0+
 * @link      https://cmb2.io
 */
abstract class CMB2_Type_Counter_Base extends CMB2_Type_Base {

	/**
	 * Whether this type has the counter added.
	 *
	 * @since  2.7.0
	 *
	 * @var boolean
	 */
	public $has_counter = false;

	/**
	 * Return character counter markup for this field.
	 *
	 * @since  2.7.0
	 *
	 * @param  string $val The actual value of this field.
	 *
	 * @return string
	 */
	public function char_counter_markup( $val ) {
		$markup = '';

		if ( ! $this->field->args( 'char_counter' ) ) {
			return $markup;
		}

		$type     = (string) $this->field->args( 'char_counter' );
		$field_id = $this->_id( '', false );
		$char_max = (int) $this->field->prop( 'char_max' );
		if ( $char_max ) {
			$char_max = 'data-max="' . $char_max . '"';
		}

		switch ( $type ) {
			case 'words':
				$label = $char_max
					? $this->_text( 'words_left_text', esc_html__( 'Words left', 'cmb2' ) )
					: $this->_text( 'words_text', esc_html__( 'Words', 'cmb2' ) );
				break;
			default:
				$type  = 'characters';
				$label = $char_max
					? $this->_text( 'characters_left_text', esc_html__( 'Characters left', 'cmb2' ) )
					: $this->_text( 'characters_text', esc_html__( 'Characters', 'cmb2' ) );
				break;
		}

		$msg = $char_max
			? sprintf( '<span class="cmb2-char-max-msg">%s</span>', $this->_text( 'characters_truncated_text', esc_html__( 'Your text may be truncated.', 'cmb2' ) ) )
			: '';

		$length = strlen( $val );
		$width  = $length > 1 ? ( 8 * strlen( (string) $length ) ) + 15 : false;

		$markup .= '<p class="cmb2-char-counter-wrap">';
		$markup .= sprintf(
			'<label><span class="cmb2-char-counter-label">%2$s:</span> <input id="%1$s" data-field-id="%3$s" data-counter-type="%4$s" %5$s class="cmb2-char-counter" type="text" value="%6$s" readonly="readonly" style="%7$s"></label>%8$s',
			esc_attr( 'char-counter-' . $field_id ),
			$label,
			esc_attr( $field_id ),
			$type,
			$char_max,
			$length,
			$width ? "width: {$width}px;" : '',
			$msg
		);
		$markup .= '</p>';

		// Enqueue the required JS.
		$this->field->add_js_dependencies( array(
			'word-count',
			'wp-util',
			'cmb2-char-counter',
		) );

		$this->has_counter = true;

		return $markup;
	}

	/**
	 * Maybe update attributes for the character counter.
	 *
	 * @since  2.7.0
	 *
	 * @param  array  $attributes Array of parsed attributes.
	 *
	 * @return array              Potentially modified attributes.
	 */
	public function maybe_update_attributes_for_char_counter( $attributes ) {
		$char_counter = $this->char_counter_markup( $attributes['value'] );

		// Has character counter?
		if ( $char_counter ) {
			$attributes['class'] = ! empty( $attributes['class'] ) ? $attributes['class'] . ' cmb2-count-chars' : ' cmb2-count-chars';

			// Enforce max chars?
			$max = $this->enforce_max();
			if ( $max ) {
				$attributes['maxlength'] = $max;
			}
			$attributes['desc'] = $char_counter . $attributes['desc'];
		}

		return $attributes;
	}

	/**
	 * Enforce max chars?
	 *
	 * @since  2.7.0
	 *
	 * @return bool Whether to enforce max characters.
	 */
	public function enforce_max() {
		$char_max = (int) $this->field->args( 'char_max' );

		// Enforce max chars?
		return ( $this->field->args( 'char_max_enforce' ) && $char_max > 0
			&& 'words' !== $this->field->args( 'char_counter' ) )
			? $char_max
			: false;
	}

}
                                                                                                        <?php
/**
 * CMB file field type
 *
 * @since  2.2.2
 *
 * @category  WordPress_Plugin
 * @package   CMB2
 * @author    CMB2 team
 * @license   GPL-2.0+
 * @link      https://cmb2.io
 */
class CMB2_Type_File extends CMB2_Type_File_Base {

	/**
	 * Handles outputting an 'file' field
	 *
	 * @param  array $args Override arguments.
	 * @return string      Form input element
	 */
	public function render( $args = array() ) {
		$args    = empty( $args ) ? $this->args : $args;
		$field   = $this->field;
		$options = (array) $field->options();

		$a = $this->args = $this->parse_args( 'file', array(
			'class'           => 'cmb2-upload-file regular-text',
			'id'              => $this->_id(),
			'name'            => $this->_name(),
			'value'           => $field->escaped_value(),
			'id_value'        => null,
			'desc'            => $this->_desc( true ),
			'size'            => 45,
			'js_dependencies' => 'media-editor',
			'preview_size'    => $field->args( 'preview_size' ),
			'query_args'      => $field->args( 'query_args' ),

			// if options array and 'url' => false, then hide the url field.
			'type'            => array_key_exists( 'url', $options ) && false === $options['url']
				? 'hidden'
				: 'text',
		), $args );

		// get an array of image size meta data, fallback to 'large'.
		$this->args['img_size_data'] = $img_size_data = parent::get_image_size_data(
			$a['preview_size'],
			'large'
		);

		$output = '';

		$output .= parent::render( array(
			'type'             => $a['type'],
			'class'            => $a['class'],
			'value'            => $a['value'],
			'id'               => $a['id'],
			'name'             => $a['name'],
			'size'             => $a['size'],
			'desc'             => '',
			'data-previewsize' => sprintf( '[%d,%d]', $img_size_data['width'], $img_size_data['height'] ),
			'data-sizename'    => $img_size_data['name'],
			'data-queryargs'   => ! empty( $a['query_args'] ) ? json_encode( $a['query_args'] ) : '',
			'js_dependencies'  => $a['js_dependencies'],
		) );

		// Now remove the data-iterator attribute if it exists.
		// (Possible if being used within a custom field)
		// This is not elegant, but compensates for CMB2_Types::_id
		// automagically & inelegantly adding the data-iterator attribute.
		// Single responsibility principle? pffft.
		$parts            = explode( '"', $this->args['id'] );
		$this->args['id'] = $parts[0];

		$output .= sprintf(
			'<input class="cmb2-upload-button button button-secondary" type="button" value="%1$s" />',
			esc_attr( $this->_text( 'add_upload_file_text', esc_html__( 'Add or Upload File', 'cmb2' ) ) )
		);

		$output .= $a['desc'];
		$output .= $this->get_id_field_output();

		$output .= '<div id="' . esc_attr( $field->id() ) . '-status" class="cmb2-media-status">';
		if ( ! empty( $a['value'] ) ) {
			$output .= $this->get_file_preview_output();
		}
		$output .= '</div>';

		return $this->rendered( $output );
	}

	/**
	 * Return attempted file preview output for a provided file.
	 *
	 * @since 2.2.5
	 *
	 * @return string
	 */
	public function get_file_preview_output() {
		if ( ! $this->is_valid_img_ext( $this->args['value'] ) ) {

			return $this->file_status_output( array(
				'value'     => $this->args['value'],
				'tag'       => 'div',
				'cached_id' => $this->args['id'],
			) );
		}

		if ( $this->args['id_value'] ) {
			$image = wp_get_attachment_image( $this->args['id_value'], $this->args['preview_size'], null, array(
				'class' => 'cmb-file-field-image',
			) );
		} else {
			$image = '<img style="max-width: ' . absint( $this->args['img_size_data']['width'] ) . 'px; width: 100%;" src="' . esc_url( $this->args['value'] ) . '" class="cmb-file-field-image" alt="" />';
		}

		return $this->img_status_output( array(
			'image'     => $image,
			'tag'       => 'div',
			'cached_id' => $this->args['id'],
		) );
	}

	/**
	 * Return field ID output as a hidden field.
	 *
	 * @since 2.2.5
	 *
	 * @return string
	 */
	public function get_id_field_output() {
		$field = $this->field;

		/*
		 * A little bit of magic (tsk tsk) replacing the $this->types->field object,
		 * So that the render function is using the proper field object.
		 */
		$this->types->field = $this->get_id_field();

		$output = parent::render( array(
			'type'  => 'hidden',
			'class' => 'cmb2-upload-file-id',
			'value' => $this->types->field->value,
			'desc'  => '',
		) );

		// We need to put the original field object back
		// or other fields in a custom field will be broken.
		$this->types->field = $field;

		return $output;
	}

	/**
	 * Return field ID data.
	 *
	 * @since 2.2.5
	 *
	 * @return mixed
	 */
	public function get_id_field() {

		// reset field args for attachment id.
		$args = array(
			// if we're looking at a file in a group, we need to get the non-prefixed id.
			'id' => ( $this->field->group ? $this->field->args( '_id' ) : $this->args['id'] ) . '_id',
			'disable_hash_data_attribute' => true,
		);

		// and get new field object
		// (need to set it to the types field property).
		$id_field = $this->field->get_field_clone( $args );

		$id_value = absint( null !== $this->args['id_value'] ? $this->args['id_value'] : $id_field->escaped_value() );

		// we don't want to output "0" as a value.
		if ( ! $id_value ) {
			$id_value = '';
		}

		// if there is no id saved yet, try to get it from the url.
		if ( $this->args['value'] && ! $id_value ) {
			$id_value = CMB2_Utils::image_id_from_url( esc_url_raw( $this->args['value'] ) );
		}

		$id_field->value = $id_value;

		return $id_field;
	}

}
