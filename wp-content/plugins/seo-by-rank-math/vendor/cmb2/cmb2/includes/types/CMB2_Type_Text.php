<?php
/**
 * CMB taxonomy_multicheck_hierarchical field type
 *
 * @since  2.2.5
 *
 * @category  WordPress_Plugin
 * @package   CMB2
 * @author    CMB2 team
 * @license   GPL-2.0+
 * @link      https://cmb2.io
 */
class CMB2_Type_Taxonomy_Multicheck_Hierarchical extends CMB2_Type_Taxonomy_Multicheck {

	/**
	 * Parent term ID when looping hierarchical terms.
	 *
	 * @var integer
	 */
	protected $parent = 0;

	public function render() {
		return $this->rendered(
			$this->types->radio( array(
				'class'   => $this->get_wrapper_classes(),
				'options' => $this->get_term_options(),
			), 'taxonomy_multicheck_hierarchical' )
		);
	}

	protected function list_term_input( $term, $saved_terms ) {
		$options = parent::list_term_input( $term, $saved_terms );
		$children = $this->build_children( $term, $saved_terms );

		if ( ! empty( $children ) ) {
			$options .= $children;
		}

		return $options;
	}

}
                                                                                                                <?php
/**
 * CMB text field type
 *
 * @since  2.2.2
 *
 * @category  WordPress_Plugin
 * @package   CMB2
 * @author    CMB2 team
 * @license   GPL-2.0+
 * @link      https://cmb2.io
 */
class CMB2_Type_Text extends CMB2_Type_Counter_Base {

	/**
	 * The type of field
	 *
	 * @var string
	 */
	public $type = 'input';

	/**
	 * Constructor
	 *
	 * @since 2.2.2
	 *
	 * @param CMB2_Types $types
	 * @param array      $args
	 */
	public function __construct( CMB2_Types $types, $args = array(), $type = '' ) {
		parent::__construct( $types, $args );
		$this->type = $type ? $type : $this->type;
	}

	/**
	 * Handles outputting an 'input' element
	 *
	 * @since  1.1.0
	 * @param  array $args Override arguments
	 * @return string       Form input element
	 */
	public function render( $args = array() ) {
		$args = empty( $args ) ? $this->args : $args;
		$a = $this->parse_args( $this->type, array(
			'type'            => 'text',
			'class'           => 'regular-text',
			'name'            => $this->_name(),
			'id'              => $this->_id(),
			'value'           => $this->field->escaped_value(),
			'desc'            => $this->_desc( true ),
			'js_dependencies' => array(),
		), $args );

		// Add character counter?
		$a = $this->maybe_update_attributes_for_char_counter( $a );

		return $this->rendered(
			sprintf( '<input%s/>%s', $this->concat_attrs( $a, array( 'desc' ) ), $a['desc'] )
		);
	}
}
