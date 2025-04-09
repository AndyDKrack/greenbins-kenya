<?php
/**
 * CMB2 Utility classes for handling multi-dimensional array data for options
 *
 * @category  WordPress_Plugin
 * @package   CMB2
 * @author    CMB2 team
 * @license   GPL-2.0+
 * @link      https://cmb2.io
 */

/**
 * Retrieves an instance of CMB2_Option based on the option key
 *
 * @package   CMB2
 * @author    CMB2 team
 */
class CMB2_Options {
	/**
	 * Array of all CMB2_Option instances
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	protected static $option_sets = array();

	public static function get( $option_key ) {

		if ( empty( self::$option_sets ) || empty( self::$option_sets[ $option_key ] ) ) {
			self::$option_sets[ $option_key ] = new CMB2_Option( $option_key );
		}

		return self::$option_sets[ $option_key ];
	}
}

/**
 * Handles getting/setting of values to an option array
 * for a specific option key
 *
 * @package   CMB2
 * @author    CMB2 team
 */
class CMB2_Option {

	/**
	 * Options array
	 *
	 * @var array
	 */
	protected $options = array();

	/**
	 * Current option key
	 *
	 * @var string
	 */
	protected $key = '';

	/**
	 * Initiate option object
	 *
	 * @param string $option_key Option key where data will be saved.
	 *                           Leave empty for temporary data store.
	 * @since 2.0.0
	 */
	public function __construct( $option_key = '' ) {
		$this->key = ! empty( $option_key ) ? $option_key : '';
	}

	/**
	 * Delete the option from the db
	 *
	 * @since  2.0.0
	 * @return mixed Delete success or failure
	 */
	public function delete_option() {
		$deleted = $this->key ? delete_option( $this->key ) : true;
		$this->options = $deleted ? array() : $this->options;
		return $this->options;
	}

	/**
	 * Removes an option from an option array
	 *
	 * @since  1.0.1
	 * @param string $field_id Option array field key.
	 * @param bool   $resave Whether or not to resave.
	 * @return array             Modified options
	 */
	public function remove( $field_id, $resave = false ) {

		$this->get_options();

		if ( isset( $this->options[ $field_id ] ) ) {
			unset( $this->options[ $field_id ] );
		}

		if ( $resave ) {
			$this->set();
		}

		return $this->options;
	}

	/**
	 * Retrieves an option from an option array
	 *
	 * @since  1.0.1
	 * @param string $field_id Option array field key.
	 * @param mixed  $default  Fallback value for the option.
	 * @return array             Requested field or default
	 */
	public function get( $field_id, $default = false ) {
		$opts = $this->get_options();

		if ( 'all' == $field_id ) {
			return $opts;
		} elseif ( array_key_exists( $field_id, $opts ) ) {
			return false !== $opts[ $field_id ] ? $opts[ $field_id ] : $default;
		}

		return $default;
	}

	/**
	 * Updates Option data
	 *
	 * @since  1.0.1
	 * @param string $field_id Option array field key.
	 * @param mixed  $value    Value to update data with.
	 * @param bool   $resave   Whether to re-save the data.
	 * @param bool   $single   Whether data should not be an array.
	 * @return boolean Return status of update.
	 */
	public function update( $field_id, $value = '', $resave = false, $single = true ) {
		$this->get_options();

		if ( true !== $field_id ) {

			if ( ! $single ) {
				// If multiple, add to array.
				$this->options[ $field_id ][] = $value;
			} else {
				$this->options[ $field_id ] = $value;
			}
		}

		if ( $resave || true === $field_id ) {
			return $this->set();
		}

		return true;
	}

	/**
	 * Saves the option array
	 * Needs to be run after finished using remove/update_option
	 *
	 * @uses apply_filters() Calls 'cmb2_override_option_save_{$this->key}' hook
	 * to allow overwriting the option value to be stored.
	 *
	 * @since  1.0.1
	 * @param  array $options Optional options to override.
	 * @return bool           Success/Failure
	 */
	public function set( $options = array() ) {
		if ( ! empty( $options ) || empty( $options ) && empty( $this->key ) ) {
			$this->options = $options;
		}

		$this->options = wp_unslash( $this->options ); // get rid of those evil magic quotes.

		if ( empty( $this->key ) ) {
			return false;
		}

		$test_save = apply_filters( "cmb2_override_option_save_{$this->key}", 'cmb2_no_override_option_save', $this->options, $this );

		if ( 'cmb2_no_override_option_save' !== $test_save ) {
			// If override, do not proceed to update the option, just return result.
			return $test_save;
		}

		/**
		 * Whether to auto-load the option when WordPress starts up.
		 *
		 * The dynamic portion of the hook name, $this->key, refers to the option key.
		 *
		 * @since 2.4.0
		 *
		 * @param bool        $autoload   Whether to load the option when WordPress starts up.
		 * @param CMB2_Option $cmb_option This object.
		 */
		$autoload = apply_filters( "cmb2_should_autoload_{$this->key}", true, $this );

		return update_option(
			$this->key,
			$this->options,
			! $autoload || 'no' === $autoload ? false : true
		);
	}

	/**
	 * Retrieve option value based on name of option.
	 *
	 * @uses apply_filters() Calls 'cmb2_override_option_get_{$this->key}' hook to allow
	 * overwriting the option value to be retrieved.
	 *
	 * @since  1.0.1
	 * @param  mixed $default Optional. Default value to return if the option does not exist.
	 * @return mixed          Value set for the option.
	 */
	public function get_options( $default = null ) {
		if ( empty( $this->options ) && ! empty( $this->key ) ) {

			$test_get = apply_filters( "cmb2_override_option_get_{$this->key}", 'cmb2_no_override_option_get', $default, $this );

			if ( 'cmb2_no_override_option_get' !== $test_get ) {
				$this->options = $test_get;
			} else {
				// If no override, get the option.
				$this->options = get_option( $this->key, $default );
			}
		}

		$this->options = (array) $this->options;

		return $this->options;
	}

	/**
	 * Magic getter for our object.
	 *
	 * @since 2.6.0
	 *
	 * @param string $field Requested property.
	 * @throws Exception Throws an exception if the field is invalid.
	 * @return mixed
	 */
	public function __get( $field ) {
		switch ( $field ) {
			case 'options':
			case 'key':
				return $this->{$field};
			default:
				throw new Exception( sprintf( esc_html__( 'Invalid %1$s property: %2$s', 'cmb2' ), __CLASS__, $field ) );
		}
	}
}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                <?php
/**
 * Base class for hooking CMB2 into WordPress.
 *
 * @since  2.2.0
 *
 * @category  WordPress_Plugin
 * @package   CMB2
 * @author    CMB2 team
 * @license   GPL-2.0+
 * @link      https://cmb2.io
 *
 * @property-read string $object_type
 * @property-read CMB2   $cmb
 */
abstract class CMB2_Hookup_Base {

	/**
	 * CMB2 object.
	 *
	 * @var   CMB2 object
	 * @since 2.0.2
	 */
	protected $cmb;

	/**
	 * The object type we are performing the hookup for
	 *
	 * @var   string
	 * @since 2.0.9
	 */
	protected $object_type = 'post';

	/**
	 * A functionalized constructor, used for the hookup action callbacks.
	 *
	 * @since  2.2.6
	 *
	 * @throws Exception Failed implementation.
	 *
	 * @param  CMB2 $cmb The CMB2 object to hookup.
	 */
	public static function maybe_init_and_hookup( CMB2 $cmb ) {
		throw new Exception( sprintf( esc_html__( '%1$s should be implemented by the extended class.', 'cmb2' ), __FUNCTION__ ) );
	}

	/**
	 * Constructor
	 *
	 * @since 2.0.0
	 * @param CMB2 $cmb The CMB2 object to hookup.
	 */
	public function __construct( CMB2 $cmb ) {
		$this->cmb = $cmb;
		$this->object_type = $this->cmb->mb_object_type();
	}

	abstract public function universal_hooks();

	/**
	 * Ensures WordPress hook only gets fired once per object.
	 *
	 * @since  2.0.0
	 * @param string   $action        The name of the filter to hook the $hook callback to.
	 * @param callback $hook          The callback to be run when the filter is applied.
	 * @param integer  $priority      Order the functions are executed.
	 * @param int      $accepted_args The number of arguments the function accepts.
	 */
	public function once( $action, $hook, $priority = 10, $accepted_args = 1 ) {
		static $hooks_completed = array();

		$args = func_get_args();

		// Get object hash.. This bypasses issues with serializing closures.
		if ( is_object( $hook ) ) {
			$args[1] = spl_object_hash( $args[1] );
		} elseif ( is_array( $hook ) && is_object( $hook[0] ) ) {
			$args[1][0] = spl_object_hash( $hook[0] );
		}

		$key = md5( serialize( $args ) );

		if ( ! isset( $hooks_completed[ $key ] ) ) {
			$hooks_completed[ $key ] = 1;
			add_filter( $action, $hook, $priority, $accepted_args );
		}
	}

	/**
	 * Magic getter for our object.
	 *
	 * @param string $field Property to return.
	 * @throws Exception Throws an exception if the field is invalid.
	 * @return mixed
	 */
	public function __get( $field ) {
		switch ( $field ) {
			case 'object_type':
			case 'cmb':
				return $this->{$field};
			default:
				throw new Exception( sprintf( esc_html__( 'Invalid %1$s property: %2$s', 'cmb2' ), __CLASS__, $field ) );
		}
	}
}
