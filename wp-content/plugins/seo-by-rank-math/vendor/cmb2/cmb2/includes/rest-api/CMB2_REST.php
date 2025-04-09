<?php
/**
 * CMB2 objects/fields endpoint for WordPres REST API.
 * Allows access to fields registered to a specific box.
 *
 * @todo  Add better documentation.
 * @todo  Research proper schema.
 *
 * @since 2.2.3
 *
 * @category  WordPress_Plugin
 * @package   CMB2
 * @author    CMB2 team
 * @license   GPL-2.0+
 * @link      https://cmb2.io
 */
class CMB2_REST_Controller_Fields extends CMB2_REST_Controller_Boxes {

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @since 2.2.3
	 */
	public function register_routes() {
		$args = array(
			'_embed' => array(
				'description' => __( 'Includes the box object which the fields are registered to in the response.', 'cmb2' ),
			),
			'_rendered' => array(
				'description' => __( 'When the \'_rendered\' argument is passed, the renderable field attributes will be returned fully rendered. By default, the names of the callback handers for the renderable attributes will be returned.', 'cmb2' ),
			),
			'object_id' => array(
				'description' => __( 'To view or modify the field\'s value, the \'object_id\' and \'object_type\' arguments are required.', 'cmb2' ),
			),
			'object_type' => array(
				'description' => __( 'To view or modify the field\'s value, the \'object_id\' and \'object_type\' arguments are required.', 'cmb2' ),
			),
		);

		// Returns specific box's fields.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<cmb_id>[\w-]+)/fields/', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'callback'            => array( $this, 'get_items' ),
				'args'                => $args,
			),
			'schema' => array( $this, 'get_item_schema' ),
		) );

		$delete_args = $args;
		$delete_args['object_id']['required'] = true;
		$delete_args['object_type']['required'] = true;

		// Returns specific field data.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<cmb_id>[\w-]+)/fields/(?P<field_id>[\w-]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'callback'            => array( $this, 'get_item' ),
				'args'                => $args,
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'callback'            => array( $this, 'update_item' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				'args'                => $args,
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'callback'            => array( $this, 'delete_item' ),
				'args'                => $delete_args,
			),
			'schema' => array( $this, 'get_item_schema' ),
		) );
	}

	/**
	 * Check if a given request has access to get fields.
	 * By default, no special permissions needed, but filtering return value.
	 *
	 * @since 2.2.3
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		$this->initiate_rest_read_box( $request, 'fields_read' );
		$can_access = true;

		/**
		 * By default, no special permissions needed.
		 *
		 * @since 2.2.3
		 *
		 * @param bool   $can_access Whether this CMB2 endpoint can be accessed.
		 * @param object $controller This CMB2_REST_Controller object.
		 */
		return $this->maybe_hook_callback_and_apply_filters( 'cmb2_api_get_fields_permissions_check', $can_access );
	}

	/**
	 * Get all public CMB2 box fields.
	 *
	 * @since 2.2.3
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		if ( ! $this->rest_box ) {
			$this->initiate_rest_read_box( $request, 'fields_read' );
		}

		if ( is_wp_error( $this->rest_box ) ) {
			return $this->rest_box;
		}

		$fields = array();
		foreach ( $this->rest_box->cmb->prop( 'fields', array() ) as $field ) {

			// Make sure this field can be read.
			$this->field = $this->rest_box->field_can_read( $field['id'], true );

			// And make sure current user can view this box.
			if ( $this->field && $this->get_item_permissions_check_filter() ) {
				$fields[ $field['id'] ] = $this->server->response_to_data(
					$this->prepare_field_response(),
					isset( $this->request['_embed'] )
				);
			}
		}

		return $this->prepare_item( $fields );
	}

	/**
	 * Check if a given request has access to a field.
	 * By default, no special permissions needed, but filtering return value.
	 *
	 * @since 2.2.3
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_item_permissions_check( $request ) {
		$this->initiate_rest_read_box( $request, 'field_read' );
		if ( ! is_wp_error( $this->rest_box ) ) {
			$this->field = $this->rest_box->field_can_read( $this->request->get_param( 'field_id' ), true );
		}

		return $this->get_item_permissions_check_filter();
	}

	/**
	 * Check by filter if a given request has access to a field.
	 * By default, no special permissions needed, but filtering return value.
	 *
	 * @since 2.2.3
	 *
	 * @param  bool $can_access Whether the current request has access to view the field by default.
	 * @return WP_Error|boolean
	 */
	public function get_item_permissions_check_filter( $can_access = true ) {
		/**
		 * By default, no special permissions needed.
		 *
		 * @since 2.2.3
		 *
		 * @param bool   $can_access Whether this CMB2 endpoint can be accessed.
		 * @param object $controller This CMB2_REST_Controller object.
		 */
		return $this->maybe_hook_callback_and_apply_filters( 'cmb2_api_get_field_permissions_check', $can_access );
	}

	/**
	 * Get one CMB2 field from the collection.
	 *
	 * @since 2.2.3
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_item( $request ) {
		$this->initiate_rest_read_box( $request, 'field_read' );

		if ( is_wp_error( $this->rest_box ) ) {
			return $this->rest_box;
		}

		return $this->prepare_read_field( $this->request->get_param( 'field_id' ) );
	}

	/**
	 * Check if a given request has access to update a field value.
	 * By default, requires 'edit_others_posts' capability, but filtering return value.
	 *
	 * @since 2.2.3
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function update_item_permissions_check( $request ) {
		$this->initiate_rest_read_box( $request, 'field_value_update' );
		if ( ! is_wp_error( $this->rest_box ) ) {
			$this->field = $this->rest_box->field_can_edit( $this->request->get_param( 'field_id' ), true );
		}

		$can_update = current_user_can( 'edit_others_posts' );

		/**
		 * By default, 'edit_others_posts' is required capability.
		 *
		 * @since 2.2.3
		 *
		 * @param bool   $can_update Whether this CMB2 endpoint can be accessed.
		 * @param object $controller This CMB2_REST_Controller object.
		 */
		return $this->maybe_hook_callback_and_apply_filters( 'cmb2_api_update_field_value_permissions_check', $can_update );
	}

	/**
	 * Update CMB2 field value.
	 *
	 * @since 2.2.3
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		$this->initiate_rest_read_box( $request, 'field_value_update' );

		if ( ! $this->request['value'] ) {
			return new WP_Error( 'cmb2_rest_update_field_error', __( 'CMB2 Field value cannot be updated without the value parameter specified.', 'cmb2' ), array(
				'status' => 400,
			) );
		}

		return $this->modify_field_value( 'updated' );
	}

	/**
	 * Check if a given request has access to delete a field value.
	 * By default, requires 'delete_others_posts' capability, but filtering return value.
	 *
	 * @since 2.2.3
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function delete_item_permissions_check( $request ) {
		$this->initiate_rest_read_box( $request, 'field_value_delete' );
		if ( ! is_wp_error( $this->rest_box ) ) {
			$this->field = $this->rest_box->field_can_edit( $this->request->get_param( 'field_id' ), true );
		}

		$can_delete = current_user_can( 'delete_others_posts' );

		/**
		 * By default, 'delete_others_posts' is required capability.
		 *
		 * @since 2.2.3
		 *
		 * @param bool   $can_delete Whether this CMB2 endpoint can be accessed.
		 * @param object $controller This CMB2_REST_Controller object.
		 */
		return $this->maybe_hook_callback_and_apply_filters( 'cmb2_api_delete_field_value_permissions_check', $can_delete );
	}

	/**
	 * Delete CMB2 field value.
	 *
	 * @since 2.2.3
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_item( $request ) {
		$this->initiate_rest_read_box( $request, 'field_value_delete' );

		return $this->modify_field_value( 'deleted' );
	}

	/**
	 * Modify CMB2 field value.
	 *
	 * @since 2.2.3
	 *
	 * @param  string $activity The modification activity (updated or deleted).
	 * @return WP_Error|WP_REST_Response
	 */
	public function modify_field_value( $activity ) {

		if ( ! $this->request['object_id'] || ! $this->request['object_type'] ) {
			return new WP_Error( 'cmb2_rest_modify_field_value_error', __( 'CMB2 Field value cannot be modified without the object_id and object_type parameters specified.', 'cmb2' ), array(
				'status' => 400,
			) );
		}

		if ( is_wp_error( $this->rest_box ) ) {
			return $this->rest_box;
		}

		$this->field = $this->rest_box->field_can_edit(
			$this->field ? $this->field : $this->request->get_param( 'field_id' ),
			true
		);

		if ( ! $this->field ) {
			return new WP_Error( 'cmb2_rest_no_field_by_id_error', __( 'No field found by that id.', 'cmb2' ), array(
				'status' => 403,
			) );
		}

		$this->field->args[ "value_{$activity}" ] = (bool) 'deleted' === $activity
			? $this->field->remove_data()
			: $this->field->save_field( $this->request['value'] );

		// If options page, save the $activity options
		if ( 'options-page' == $this->request['object_type'] ) {
			$this->field->args[ "value_{$activity}" ] = cmb2_options( $this->request['object_id'] )->set();
		}

		return $this->prepare_read_field( $this->field );
	}

	/**
	 * Get a response object for a specific field ID.
	 *
	 * @since 2.2.3
	 *
	 * @param  string\CMB2_Field Field id or Field object.
	 * @return WP_Error|WP_REST_Response
	 */
	public function prepare_read_field( $field ) {
		$this->field = $this->rest_box->field_can_read( $field, true );

		if ( ! $this->field ) {
			return new WP_Error( 'cmb2_rest_no_field_by_id_error', __( 'No field found by that id.', 'cmb2' ), array(
				'status' => 403,
			) );
		}

		return $this->prepare_item( $this->prepare_field_response() );
	}

	/**
	 * Get a specific field response.
	 *
	 * @since 2.2.3
	 *
	 * @param  CMB2_Field Field object.
	 * @return array      Response array.
	 */
	public function prepare_field_response() {
		$field_data = $this->prepare_field_data( $this->field );
		$response = rest_ensure_response( $field_data );

		$response->add_links( $this->prepare_links( $this->field ) );

		return $response;
	}

	/**
	 * Prepare the field data array for JSON.
	 *
	 * @since  2.2.3
	 *
	 * @param  CMB2_Field $field field object.
	 *
	 * @return array             Array of field data.
	 */
	protected function prepare_field_data( CMB2_Field $field ) {
		$field_data = array();
		$params_to_ignore = array( 'show_in_rest', 'options' );
		$params_to_rename = array(
			'label_cb' => 'label',
			'options_cb' => 'options',
		);

		// Run this first so the js_dependencies arg is populated.
		$rendered = ( $cb = $field->maybe_callback( 'render_row_cb' ) )
			// Ok, callback is good, let's run it.
			? $this->get_cb_results( $cb, $field->args(), $field )
			: false;

		$field_args = $field->args();

		foreach ( $field_args as $key => $value ) {
			if ( in_array( $key, $params_to_ignore, true ) ) {
				continue;
			}

			if ( 'options_cb' === $key ) {
				$value = $field->options();
			} elseif ( in_array( $key, CMB2_Field::$callable_fields, true ) ) {

				if ( isset( $this->request['_rendered'] ) ) {
					$value = $key === 'render_row_cb' ? $rendered : $field->get_param_callback_result( $key );
				} elseif ( is_array( $value ) ) {
					// We need to rewrite callbacks as string as they will cause
					// JSON recursion errors.
					$class = is_string( $value[0] ) ? $value[0] : get_class( $value[0] );
					$value = $class . '::' . $value[1];
				}
			}

			$key = isset( $params_to_rename[ $key ] ) ? $params_to_rename[ $key ] : $key;

			if ( empty( $value ) || is_scalar( $value ) || is_array( $value ) ) {
				$field_data[ $key ] = $value;
			} else {
				$field_data[ $key ] = sprintf( __( 'Value Error for %s', 'cmb2' ), $key );
			}
		}

		if ( $field->args( 'has_supporting_data' ) ) {
			$field_data = $this->get_supporting_data( $field_data, $field );
		}

		if ( $this->request['object_id'] && $this->request['object_type'] ) {
			$field_data['value'] = $field->get_rest_value();
		}

		return $field_data;
	}

	/**
	 * Gets field supporting data (field id and value).
	 *
	 * @since  2.7.0
	 *
	 * @param  CMB2_Field $field      Field object.
	 * @param  array      $field_data Array of field data.
	 *
	 * @return array                  Array of field data.
	 */
	public function get_supporting_data( $field_data, $field ) {

		// Reset placement of this property.
		unset( $field_data['has_supporting_data'] );
		$field_data['has_supporting_data'] = true;

		$field = $field->get_supporting_field();
		$field_data['supporting_data'] = array(
			'id' => $field->_id( '', false ),
		);

		if ( $this->request['object_id'] && $this->request['object_type'] ) {
			$field_data['supporting_data']['value'] = $field->get_rest_value();
		}

		return $field_data;
	}

	/**
	 * Return an array of contextual links for field/fields.
	 *
	 * @since  2.2.3
	 *
	 * @param  CMB2_Field $field Field object to build links from.
	 *
	 * @return array             Array of links
	 */
	protected function prepare_links( $field ) {
		$boxbase      = $this->namespace_base . '/' . $this->rest_box->cmb->cmb_id;
		$query_string = $this->get_query_string();

		$links = array(
			'self' => array(
				'href' => rest_url( trailingslashit( $boxbase ) . 'fields/' . $field->_id( '', false ) . $query_string ),
			),
			'collection' => array(
				'href' => rest_url( trailingslashit( $boxbase ) . 'fields' . $query_string ),
			),
			'up' => array(
				'embeddable' => true,
				'href' => rest_url( $boxbase . $query_string ),
			),
		);

		return $links;
	}

	/**
	 * Checks if the CMB2 box or field has any registered callback parameters for the given filter.
	 *
	 * The registered handlers will have a property name which matches the filter, except:
	 * - The 'cmb2_api' prefix will be removed
	 * - A '_cb' suffix will be added (to stay inline with other '*_cb' parameters).
	 *
	 * @since  2.2.3
	 *
	 * @param  string $filter      The filter name.
	 * @param  bool   $default_val The default filter value.
	 *
	 * @return bool                The possibly-modified filter value (if the _cb param is a non-callable).
	 */
	public function maybe_hook_registered_callback( $filter, $default_val ) {
		$default_val = parent::maybe_hook_registered_callback( $filter, $default_val );

		if ( $this->field ) {

			// Hook field specific filter callbacks.
			$val = $this->field->maybe_hook_parameter( $filter, $default_val );
			if ( null !== $val ) {
				$default_val = $val;
			}
		}

		return $default_val;
	}

	/**
	 * Unhooks any CMB2 box or field registered callback parameters for the given filter.
	 *
	 * @since  2.2.3
	 *
	 * @param  string $filter The filter name.
	 *
	 * @return void
	 */
	public function maybe_unhook_registered_callback( $filter ) {
		parent::maybe_unhook_registered_callback( $filter );

		if ( $this->field ) {
			// Unhook field specific filter callbacks.
			$this->field->maybe_hook_parameter( $filter, null, 'remove_filter' );
		}
	}

}
                                                          <?php
/**
 * Handles hooking CMB2 objects/fields into the WordPres REST API
 * which can allow fields to be read and/or updated.
 *
 * @since  2.2.3
 *
 * @category  WordPress_Plugin
 * @package   CMB2
 * @author    CMB2 team
 * @license   GPL-2.0+
 * @link      https://cmb2.io
 *
 * @property-read read_fields Array of readable field objects.
 * @property-read edit_fields Array of editable field objects.
 * @property-read rest_read   Whether CMB2 object is readable via the rest api.
 * @property-read rest_edit   Whether CMB2 object is editable via the rest api.
 */
class CMB2_REST extends CMB2_Hookup_Base {

	/**
	 * The current CMB2 REST endpoint version
	 *
	 * @var string
	 * @since 2.2.3
	 */
	const VERSION = '1';

	/**
	 * The CMB2 REST base namespace (v should always be followed by $version)
	 *
	 * @var string
	 * @since 2.2.3
	 */
	const NAME_SPACE = 'cmb2/v1';

	/**
	 * @var   CMB2 object
	 * @since 2.2.3
	 */
	public $cmb;

	/**
	 * @var   CMB2_REST[] objects
	 * @since 2.2.3
	 */
	protected static $boxes = array();

	/**
	 * @var   array Array of cmb ids for each type.
	 * @since 2.2.3
	 */
	protected static $type_boxes = array(
		'post' => array(),
		'user' => array(),
		'comment' => array(),
		'term' => array(),
	);

	/**
	 * Array of readable field objects.
	 *
	 * @var   CMB2_Field[]
	 * @since 2.2.3
	 */
	protected $read_fields = array();

	/**
	 * Array of editable field objects.
	 *
	 * @var   CMB2_Field[]
	 * @since 2.2.3
	 */
	protected $edit_fields = array();

	/**
	 * Whether CMB2 object is readable via the rest api.
	 *
	 * @var boolean
	 */
	protected $rest_read = false;

	/**
	 * Whether CMB2 object is editable via the rest api.
	 *
	 * @var boolean
	 */
	protected $rest_edit = false;

	/**
	 * A functionalized constructor, used for the hookup action callbacks.
	 *
	 * @since  2.2.6
	 *
	 * @param  CMB2 $cmb The CMB2 object to hookup
	 *
	 * @return CMB2_Hookup_Base $hookup The hookup object.
	 */
	public static function maybe_init_and_hookup( CMB2 $cmb ) {
		if ( $cmb->prop( 'show_in_rest' ) && function_exists( 'rest_get_server' ) ) {

			$hookup = new self( $cmb );

			return $hookup->universal_hooks();
		}

		return false;
	}

	/**
	 * Constructor
	 *
	 * @since 2.2.3
	 *
	 * @param CMB2 $cmb The CMB2 object to be registered for the API.
	 */
	public function __construct( CMB2 $cmb ) {
		$this->cmb = $cmb;
		self::$boxes[ $cmb->cmb_id ] = $this;

		$show_value = $this->cmb->prop( 'show_in_rest' );

		$this->rest_read = self::is_readable( $show_value );
		$this->rest_edit = self::is_editable( $show_value );
	}

	/**
	 * Hooks to register on frontend and backend.
	 *
	 * @since  2.2.3
	 *
	 * @return void
	 */
	public function universal_hooks() {
		// hook up the CMB rest endpoint classes
		$this->once( 'rest_api_init', array( __CLASS__, 'init_routes' ), 0 );

		if ( function_exists( 'register_rest_field' ) ) {
			$this->once( 'rest_api_init', array( __CLASS__, 'register_cmb2_fields' ), 50 );
		}

		$this->declare_read_edit_fields();

		add_filter( 'is_protected_meta', array( $this, 'is_protected_meta' ), 10, 3 );

		return $this;
	}

	/**
	 * Initiate the CMB2 Boxes and Fields routes
	 *
	 * @since  2.2.3
	 *
	 * @return void
	 */
	public static function init_routes() {
		$wp_rest_server = rest_get_server();

		$boxes_controller = new CMB2_REST_Controller_Boxes( $wp_rest_server );
		$boxes_controller->register_routes();

		$fields_controller = new CMB2_REST_Controller_Fields( $wp_rest_server );
		$fields_controller->register_routes();
	}

	/**
	 * Loop through REST boxes and call register_rest_field for each object type.
	 *
	 * @since  2.2.3
	 *
	 * @return void
	 */
	public static function register_cmb2_fields() {
		$alltypes = $taxonomies = array();

		foreach ( self::$boxes as $cmb_id => $rest_box ) {

			// Hook box specific filter callbacks.
			$callback = $rest_box->cmb->prop( 'register_rest_field_cb' );
			if ( is_callable( $callback ) ) {
				call_user_func( $callback, $rest_box );
				continue;
			}

			$types = array_flip( $rest_box->cmb->box_types( array( 'post' ) ) );

			if ( isset( $types['user'] ) ) {
				unset( $types['user'] );
				self::$type_boxes['user'][ $cmb_id ] = $cmb_id;
			}

			if ( isset( $types['comment'] ) ) {
				unset( $types['comment'] );
				self::$type_boxes['comment'][ $cmb_id ] = $cmb_id;
			}

			if ( isset( $types['term'] ) ) {
				unset( $types['term'] );

				$taxonomies = array_merge(
					$taxonomies,
					CMB2_Utils::ensure_array( $rest_box->cmb->prop( 'taxonomies' ) )
				);

				self::$type_boxes['term'][ $cmb_id ] = $cmb_id;
			}

			if ( ! empty( $types ) ) {
				$alltypes = array_merge( $alltypes, array_flip( $types ) );
				self::$type_boxes['post'][ $cmb_id ] = $cmb_id;
			}
		}

		$alltypes = array_unique( $alltypes );

		if ( ! empty( $alltypes ) ) {
			self::register_rest_field( $alltypes, 'post' );
		}

		if ( ! empty( self::$type_boxes['user'] ) ) {
			self::register_rest_field( 'user', 'user' );
		}

		if ( ! empty( self::$type_boxes['comment'] ) ) {
			self::register_rest_field( 'comment', 'comment' );
		}

		if ( ! empty( self::$type_boxes['term'] ) ) {
			self::register_rest_field( $taxonomies, 'term' );
		}
	}

	/**
	 * Wrapper for register_rest_field.
	 *
	 * @since  2.2.3
	 *
	 * @param string|array $object_types Object(s) the field is being registered
	 *                                   to, "post"|"term"|"comment" etc.
	 * @param string       $object_types       Canonical object type for callbacks.
	 *
	 * @return void
	 */
	protected static function register_rest_field( $object_types, $object_type ) {
		register_rest_field( $object_types, 'cmb2', array(
			'get_callback'    => array( __CLASS__, "get_{$object_type}_rest_values" ),
			'update_callback' => array( __CLASS__, "update_{$object_type}_rest_values" ),
			'schema'          => null, // @todo add schema
		) );
	}

	/**
	 * Setup readable and editable fields.
	 *
	 * @since  2.2.3
	 *
	 * @return void
	 */
	protected function declare_read_edit_fields() {
		foreach ( $this->cmb->prop( 'fields' ) as $field ) {
			$show_in_rest = isset( $field['show_in_rest'] ) ? $field['show_in_rest'] : null;

			if ( false === $show_in_rest ) {
				continue;
			}

			if ( $this->can_read( $show_in_rest ) ) {
				$this->read_fields[] = $field['id'];
			}

			if ( $this->can_edit( $show_in_rest ) ) {
				$this->edit_fields[] = $field['id'];
			}
		}
	}

	/**
	 * Determines if a field is readable based on it's show_in_rest value
	 * and the box's show_in_rest value.
	 *
	 * @since  2.2.3
	 *
	 * @param  bool $show_in_rest Field's show_in_rest value. Default null.
	 *
	 * @return bool               Whether field is readable.
	 */
	protected function can_read( $show_in_rest ) {
		// if 'null', then use default box value.
		if ( null === $show_in_rest ) {
			return $this->rest_read;
		}

		// Else check if the value represents readable.
		return self::is_readable( $show_in_rest );
	}

	/**
	 * Determines if a field is editable based on it's show_in_rest value
	 * and the box's show_in_rest value.
	 *
	 * @since  2.2.3
	 *
	 * @param  bool $show_in_rest Field's show_in_rest value. Default null.
	 *
	 * @return bool               Whether field is editable.
	 */
	protected function can_edit( $show_in_rest ) {
		// if 'null', then use default box value.
		if ( null === $show_in_rest ) {
			return $this->rest_edit;
		}

		// Else check if the value represents editable.
		return self::is_editable( $show_in_rest );
	}

	/**
	 * Handler for getting post custom field data.
	 *
	 * @since  2.2.3
	 *
	 * @param  array           $object      The object data from the response
	 * @param  string          $field_name  Name of field
	 * @param  WP_REST_Request $request     Current request
	 * @param  string          $object_type The request object type
	 *
	 * @return mixed
	 */
	public static function get_post_rest_values( $object, $field_name, $request, $object_type ) {
		if ( 'cmb2' === $field_name ) {
			return self::get_rest_values( $object, $request, $object_type, 'post' );
		}
	}

	/**
	 * Handler for getting user custom field data.
	 *
	 * @since  2.2.3
	 *
	 * @param  array           $object      The object data from the response
	 * @param  string          $field_name  Name of field
	 * @param  WP_REST_Request $request     Current request
	 * @param  string          $object_type The request object type
	 *
	 * @return mixed
	 */
	public static function get_user_rest_values( $object, $field_name, $request, $object_type ) {
		if ( 'cmb2' === $field_name ) {
			return self::get_rest_values( $object, $request, $object_type, 'user' );
		}
	}

	/**
	 * Handler for getting comment custom field data.
	 *
	 * @since  2.2.3
	 *
	 * @param  array           $object      The object data from the response
	 * @param  string          $field_name  Name of field
	 * @param  WP_REST_Request $request     Current request
	 * @param  string          $object_type The request object type
	 *
	 * @return mixed
	 */
	public static function get_comment_rest_values( $object, $field_name, $request, $object_type ) {
		if ( 'cmb2' === $field_name ) {
			return self::get_rest_values( $object, $request, $object_type, 'comment' );
		}
	}

	/**
	 * Handler for getting term custom field data.
	 *
	 * @since  2.2.3
	 *
	 * @param  array           $object      The object data from the response
	 * @param  string          $field_name  Name of field
	 * @param  WP_REST_Request $request     Current request
	 * @param  string          $object_type The request object type
	 *
	 * @return mixed
	 */
	public static function get_term_rest_values( $object, $field_name, $request, $object_type ) {
		if ( 'cmb2' === $field_name ) {
			return self::get_rest_values( $object, $request, $object_type, 'term' );
		}
	}

	/**
	 * Handler for getting custom field data.
	 *
	 * @since  2.2.3
	 *
	 * @param  array           $object           The object data from the response
	 * @param  WP_REST_Request $request          Current request
	 * @param  string          $object_type      The request object type
	 * @param  string          $main_object_type The cmb main object type
	 *
	 * @return mixed
	 */
	protected static function get_rest_values( $object, $request, $object_type, $main_object_type = 'post' ) {
		if ( ! isset( $object['id'] ) ) {
			return;
		}

		$values = array();

		if ( ! empty( self::$type_boxes[ $main_object_type ] ) ) {
			foreach ( self::$type_boxes[ $main_object_type ] as $cmb_id ) {
				$rest_box = self::$boxes[ $cmb_id ];

				if ( ! $rest_box->cmb->is_box_type( $object_type ) ) {
					continue;
				}

				$result = self::get_box_rest_values( $rest_box, $object['id'], $main_object_type );
				if ( ! empty( $result ) ) {
					if ( empty( $values[ $cmb_id ] ) ) {
						$values[ $cmb_id ] = $result;
					} else {
						$values[ $cmb_id ] = array_merge( $values[ $cmb_id ], $result );
					}
				}
			}
		}

		return $values;
	}

	/**
	 * Get box rest values.
	 *
	 * @since  2.7.0
	 *
	 * @param  CMB2_REST $rest_box         The CMB2_REST object.
	 * @param  integer   $object_id        The object ID.
	 * @param  string    $main_object_type The object type (post, user, term, etc)
	 *
	 * @return array                       Array of box rest values.
	 */
	public static function get_box_rest_values( $rest_box, $object_id = 0, $main_object_type = 'post' ) {

		$rest_box->cmb->object_id( $object_id );
		$rest_box->cmb->object_type( $main_object_type );

		$values = array();

		foreach ( $rest_box->read_fields as $field_id ) {
			$field = $rest_box->cmb->get_field( $field_id );
			$field->object_id( $object_id );
			$field->object_type( $main_object_type );

			$values[ $field->id( true ) ] = $field->get_rest_value();

			if ( $field->args( 'has_supporting_data' ) ) {
				$field = $field->get_supporting_field();
				$values[ $field->id( true ) ] = $field->get_rest_value();
			}
		}

		return $values;
	}

	/**
	 * Handler for updating post custom field data.
	 *
	 * @since  2.2.3
	 *
	 * @param  mixed           $values      The value of the field
	 * @param  object          $object      The object from the response
	 * @param  string          $field_name  Name of field
	 * @param  WP_REST_Request $request     Current request
	 * @param  string          $object_type The request object type
	 *
	 * @return bool|int
	 */
	public static function update_post_rest_values( $values, $object, $field_name, $request, $object_type ) {
		if ( 'cmb2' === $field_name ) {
			return self::update_rest_values( $values, $object, $request, $object_type, 'post' );
		}
	}

	/**
	 * Handler for updating user custom field data.
	 *
	 * @since  2.2.3
	 *
	 * @param  mixed           $values      The value of the field
	 * @param  object          $object      The object from the response
	 * @param  string          $field_name  Name of field
	 * @param  WP_REST_Request $request     Current request
	 * @param  string          $object_type The request object type
	 *
	 * @return bool|int
	 */
	public static function update_user_rest_values( $values, $object, $field_name, $request, $object_type ) {
		if ( 'cmb2' === $field_name ) {
			return self::update_rest_values( $values, $object, $request, $object_type, 'user' );
		}
	}

	/**
	 * Handler for updating comment custom field data.
	 *
	 * @since  2.2.3
	 *
	 * @param  mixed           $values      The value of the field
	 * @param  object          $object      The object from the response
	 * @param  string          $field_name  Name of field
	 * @param  WP_REST_Request $request     Current request
	 * @param  string          $object_type The request object type
	 *
	 * @return bool|int
	 */
	public static function update_comment_rest_values( $values, $object, $field_name, $request, $object_type ) {
		if ( 'cmb2' === $field_name ) {
			return self::update_rest_values( $values, $object, $request, $object_type, 'comment' );
		}
	}

	/**
	 * Handler for updating term custom field data.
	 *
	 * @since  2.2.3
	 *
	 * @param  mixed           $values      The value of the field
	 * @param  object          $object      The object from the response
	 * @param  string          $field_name  Name of field
	 * @param  WP_REST_Request $request     Current request
	 * @param  string          $object_type The request object type
	 *
	 * @return bool|int
	 */
	public static function update_term_rest_values( $values, $object, $field_name, $request, $object_type ) {
		if ( 'cmb2' === $field_name ) {
			return self::update_rest_values( $values, $object, $request, $object_type, 'term' );
		}
	}

	/**
	 * Handler for updating custom field data.
	 *
	 * @since  2.2.3
	 *
	 * @param  mixed           $values           The value of the field
	 * @param  object          $object           The object from the response
	 * @param  WP_REST_Request $request          Current request
	 * @param  string          $object_type      The request object type
	 * @param  string          $main_object_type The cmb main object type
	 *
	 * @return bool|int
	 */
	protected static function update_rest_values( $values, $object, $request, $object_type, $main_object_type = 'post' ) {
		if ( empty( $values ) || ! is_array( $values ) ) {
			return;
		}

		$object_id = self::get_object_id( $object, $main_object_type );

		if ( ! $object_id ) {
			return;
		}

		$updated = array();

		if ( ! empty( self::$type_boxes[ $main_object_type ] ) ) {
			foreach ( self::$type_boxes[ $main_object_type ] as $cmb_id ) {
				$result = self::santize_box_rest_values( $values, self::$boxes[ $cmb_id ], $object_id, $main_object_type );
				if ( ! empty( $result ) ) {
					$updated[ $cmb_id ] = $result;
				}
			}
		}

		return $updated;
	}

	/**
	 * Updates box rest values.
	 *
	 * @since  2.7.0
	 *
	 * @param  array     $values           Array of values.
	 * @param  CMB2_REST $rest_box         The CMB2_REST object.
	 * @param  integer   $object_id        The object ID.
	 * @param  string    $main_object_type The object type (post, user, term, etc)
	 *
	 * @return mixed|bool                  Array of updated statuses if successful.
	 */
	public static function santize_box_rest_values( $values, $rest_box, $object_id = 0, $main_object_type = 'post' ) {

		if ( ! array_key_exists( $rest_box->cmb->cmb_id, $values ) ) {
			return false;
		}

		$rest_box->cmb->object_id( $object_id );
		$rest_box->cmb->object_type( $main_object_type );

		return $rest_box->sanitize_box_values( $values );
	}

	/**
	 * Loop through box fields and sanitize the values.
	 *
	 * @since  2.2.o
	 *
	 * @param  array $values Array of values being provided.
	 * @return array           Array of updated/sanitized values.
	 */
	public function sanitize_box_values( array $values ) {
		$updated = array();

		$this->cmb->pre_process();

		foreach ( $this->edit_fields as $field_id ) {
			$updated[ $field_id ] = $this->sanitize_field_value( $values, $field_id );
		}

		$this->cmb->after_save();

		return $updated;
	}

	/**
	 * Handles returning a sanitized field value.
	 *
	 * @since  2.2.3
	 *
	 * @param  array  $values   Array of values being provided.
	 * @param  string $field_id The id of the field to update.
	 *
	 * @return mixed             The results of saving/sanitizing a field value.
	 */
	protected function sanitize_field_value( array $values, $field_id ) {
		if ( ! array_key_exists( $field_id, $values[ $this->cmb->cmb_id ] ) ) {
			return;
		}

		$field = $this->cmb->get_field( $field_id );

		if ( 'title' == $field->type() ) {
			return;
		}

		$field->object_id( $this->cmb->object_id() );
		$field->object_type( $this->cmb->object_type() );

		if ( 'group' == $field->type() ) {
			return $this->sanitize_group_value( $values, $field );
		}

		return $field->save_field( $values[ $this->cmb->cmb_id ][ $field_id ] );
	}

	/**
	 * Handles returning a sanitized group field value.
	 *
	 * @since  2.2.3
	 *
	 * @param  array      $values Array of values being provided.
	 * @param  CMB2_Field $field  CMB2_Field object.
	 *
	 * @return mixed               The results of saving/sanitizing the group field value.
	 */
	protected function sanitize_group_value( array $values, CMB2_Field $field ) {
		$fields = $field->fields();
		if ( empty( $fields ) ) {
			return;
		}

		$this->cmb->data_to_save[ $field->_id( '', false ) ] = $values[ $this->cmb->cmb_id ][ $field->_id( '', false ) ];

		return $this->cmb->save_group_field( $field );
	}

	/**
	 * Filter whether a meta key is protected.
	 *
	 * @since 2.2.3
	 *
	 * @param bool   $protected Whether the key is protected. Default false.
	 * @param string $meta_key  Meta key.
	 * @param string $meta_type Meta type.
	 */
	public function is_protected_meta( $protected, $meta_key, $meta_type ) {
		if ( $this->field_can_edit( $meta_key ) ) {
			return false;
		}

		return $protected;
	}

	/**
	 * Get the object ID for the given object/type.
	 *
	 * @since  2.2.3
	 *
	 * @param  mixed  $object      The object to get the ID for.
	 * @param  string $object_type The object type we are looking for.
	 *
	 * @return int                 The object ID if found.
	 */
	public static function get_object_id( $object, $object_type = 'post' ) {
		switch ( $object_type ) {
			case 'user':
			case 'post':
				if ( isset( $object->ID ) ) {
					return intval( $object->ID );
				}
			case 'comment':
				if ( isset( $object->comment_ID ) ) {
					return intval( $object->comment_ID );
				}
			case 'term':
				if ( is_array( $object ) && isset( $object['term_id'] ) ) {
					return intval( $object['term_id'] );
				} elseif ( isset( $object->term_id ) ) {
					return intval( $object->term_id );
				}
		}

		return 0;
	}

	/**
	 * Checks if a given field can be read.
	 *
	 * @since  2.2.3
	 *
	 * @param  string|CMB2_Field $field_id      Field ID or CMB2_Field object.
	 * @param  boolean           $return_object Whether to return the Field object.
	 *
	 * @return mixed                            False if field can't be read or true|CMB2_Field object.
	 */
	public function field_can_read( $field_id, $return_object = false ) {
		return $this->field_can( 'read_fields', $field_id, $return_object );
	}

	/**
	 * Checks if a given field can be edited.
	 *
	 * @since  2.2.3
	 *
	 * @param  string|CMB2_Field $field_id      Field ID or CMB2_Field object.
	 * @param  boolean           $return_object Whether to return the Field object.
	 *
	 * @return mixed                            False if field can't be edited or true|CMB2_Field object.
	 */
	public function field_can_edit( $field_id, $return_object = false ) {
		return $this->field_can( 'edit_fields', $field_id, $return_object );
	}

	/**
	 * Checks if a given field can be read or edited.
	 *
	 * @since  2.2.3
	 *
	 * @param  string            $type          Whether we are checking for read or edit fields.
	 * @param  string|CMB2_Field $field_id      Field ID or CMB2_Field object.
	 * @param  boolean           $return_object Whether to return the Field object.
	 *
	 * @return mixed                            False if field can't be read or edited or true|CMB2_Field object.
	 */
	protected function field_can( $type = 'read_fields', $field_id, $return_object = false ) {
		if ( ! in_array( $field_id instanceof CMB2_Field ? $field_id->id() : $field_id, $this->{$type}, true ) ) {
			return false;
		}

		return $return_object ? $this->cmb->get_field( $field_id ) : true;
	}

	/**
	 * Get a CMB2_REST instance object from the registry by a CMB2 id.
	 *
	 * @since  2.2.3
	 *
	 * @param  string $cmb_id CMB2 config id
	 *
	 * @return CMB2_REST|false The CMB2_REST object or false.
	 */
	public static function get_rest_box( $cmb_id ) {
		return isset( self::$boxes[ $cmb_id ] ) ? self::$boxes[ $cmb_id ] : false;
	}

	/**
	 * Remove a CMB2_REST instance object from the registry.
	 *
	 * @since  2.2.3
	 *
	 * @param string $cmb_id A CMB2 instance id.
	 */
	public static function remove( $cmb_id ) {
		if ( array_key_exists( $cmb_id, self::$boxes ) ) {
			unset( self::$boxes[ $cmb_id ] );
		}
	}

	/**
	 * Retrieve all CMB2_REST instances from the registry.
	 *
	 * @since  2.2.3
	 * @return CMB2[] Array of all registered CMB2_REST instances.
	 */
	public static function get_all() {
		return self::$boxes;
	}

	/**
	 * Checks if given value is readable.
	 *
	 * Value is considered readable if it is not empty and if it does not match the editable blacklist.
	 *
	 * @since  2.2.3
	 *
	 * @param  mixed $value Value to check.
	 *
	 * @return boolean       Whether value is considered readable.
	 */
	public static function is_readable( $value ) {
		return ! empty( $value ) && ! in_array( $value, array(
			WP_REST_Server::CREATABLE,
			WP_REST_Server::EDITABLE,
			WP_REST_Server::DELETABLE,
		), true );
	}

	/**
	 * Checks if given value is editable.
	 *
	 * Value is considered editable if matches the editable whitelist.
	 *
	 * @since  2.2.3
	 *
	 * @param  mixed $value Value to check.
	 *
	 * @return boolean       Whether value is considered editable.
	 */
	public static function is_editable( $value ) {
		return in_array( $value, array(
			WP_REST_Server::EDITABLE,
			WP_REST_Server::ALLMETHODS,
		), true );
	}

	/**
	 * Magic getter for our object.
	 *
	 * @param string $field
	 * @throws Exception Throws an exception if the field is invalid.
	 *
	 * @return mixed
	 */
	public function __get( $field ) {
		switch ( $field ) {
			case 'read_fields':
			case 'edit_fields':
			case 'rest_read':
			case 'rest_edit':
				return $this->{$field};
			default:
				throw new Exception( 'Invalid ' . __CLASS__ . ' property: ' . $field );
		}
	}

}
