<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

/**
 * Skouerr_CLI_Make_Block class handles the creation of a new block using
 * WP-CLI (WordPress Command Line Interface). It prompts the user for input
 * to select the block type, template, name, prefix, and icon. It then creates
 * the block based on the provided data.
 */
class Skouerr_CLI_Make_Block {


	public array $types;
	public array $templates;
	public array $prefix;

	/**
	 * Constructor for the Skouerr_CLI_Make_Block class.
	 * Initializes the block types, templates, and prefix arrays.
	 *
	 * @return void
	 */
	public function __construct() {

		$acf_text = __( 'ACF (needs ACF Pro)' );

		$this->types = array(
			'native' => 'Native',
			'acf' => $acf_text,
		);

		$this->templates = array(
			'native' => array(
				// 'react' => 'React',
				'php' => 'PHP',
				'twig' => 'Twig / Timber',
			),
			'acf' => array(
				'php' => 'PHP',
				'twig' => 'Twig / Timber',
				// 'react' => 'React',
			),
		);

		$this->prefix = array(
			'skouerr',
			get_template(),
			'acf',
			'core',
			'custom',
		);
	}

	/**
	 * Creates a new block by forming the block data and invoking the create_block method.
	 * Displays a success message upon completion.
	 *
	 * @return void
	 */
	public function make_block() {
		$block_data = $this->form_block();
		$this->create_block( $block_data );
		WP_CLI::success( __( 'Block created successfully' ) );
	}

	/**
	 * Forms the block data by prompting the user for input.
	 * It performs the following steps:
	 * - Prompts the user to select the type of block.
	 * - Prompts the user to select the template for the block.
	 * - Prompts the user to enter the name of the block.
	 * - Prompts the user to select the prefix for the block.
	 * - Generates the block slug from the prefix and name.
	 * - Prompts the user to enter the dashicon for the block.
	 * - Displays the entered information for confirmation.
	 * - Prompts the user to confirm the block creation.
	 * - Returns the block data if confirmed, otherwise aborts the process.
	 *
	 * @return array The block data including title, name, type, template, slug, and icon.
	 */
	public function form_block() {
		$type = SK_CLI_Input::select( __( 'Select the type of block' ), $this->types );

		$templates = $this->templates[ $type ];
		$template = SK_CLI_Input::select( __( 'Select the template' ), $templates );

		$title = SK_CLI_Input::ask( __( 'Enter the name of the block' ) );
		$title = ucwords( $title );
		$prefix = SK_CLI_Input::select( __( 'Select the prefix' ), $this->prefix );
		$name = strtolower( str_replace( ' ', '-', $title ) );
		$slug = $prefix . '/' . $name;

		$dashicons = $this->get_dashicons_list();

		$icon = SK_CLI_Input::ask( __( 'Enter the dashicon of the block (https://developer.wordpress.org/resource/dashicons)' ), 'block-default', $dashicons );
		$icon = str_replace( 'dashicons-', '', $icon );

		WP_CLI::line( __( 'Confirm the following information:' ) );
		WP_CLI::line( __( ' Title: ' ) . $title );
		WP_CLI::line( __( ' Name: ' ) . $name );
		WP_CLI::line( __( ' Type: ' ) . $type );
		WP_CLI::line( __( ' Template: ' ) . $template );
		WP_CLI::line( __( ' Slug: ' ) . $slug );
		WP_CLI::line( __( ' Icon: ' ) . $icon );

		$confirm = SK_CLI_Input::select( __( 'Do you confirm creation of block ?' ), array( 'yes', 'no' ) );
		if ( $confirm == 'no' ) {
			WP_CLI::line( __( 'Block creation aborted' ) );
			die();
		}
		$block_data = array(
			'title' => $title,
			'name' => $name,
			'type' => $type,
			'template' => $template,
			'slug' => $slug,
			'icon' => $icon,
		);
		return $block_data;
	}

	/**
	 * Creates a new block based on the provided block data.
	 * It instantiates the appropriate block class depending on the block type and template.
	 *
	 * @param array $block_data The data for the block, including type and template.
	 * @return void
	 */
	public function create_block( $block_data ) {
		if ( $block_data['type'] == 'native' && $block_data['template'] == 'php' ) {
			new Skouerr_Template_Block_Native_Php( $block_data );
		} else if ( $block_data['type'] == 'native' && $block_data['template'] == 'twig' ) {
			new Skouerr_Template_Block_Native_Twig( $block_data );
		} else if ( $block_data['type'] == 'acf' && $block_data['template'] == 'php' ) {
			new Skouerr_Template_Block_Acf_Php( $block_data );
		} else if ( $block_data['type'] == 'acf' && $block_data['template'] == 'twig' ) {
			new Skouerr_Template_Block_Acf_Twig( $block_data );
		} else {
			WP_CLI::error( __( 'This template is not available' ) );
		}
	}

	/**
	 * Creates a new block based on the provided block data.
	 * It instantiates the appropriate block class depending on the block type and template.
	 *
	 * @return array An array of dashicons with their IDs and names.
	 */
	public function get_dashicons_list(): array {

		$items = array(
			array(
				'group' => 'admin',
				'id'    => 'dashicons-admin-appearance',
				'name'  => __( 'Appearance', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-admin-collapse',
				'name'  => __( 'Collapse', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-admin-comments',
				'name'  => __( 'Comments', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-admin-customizer',
				'name'  => __( 'Customizer', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-dashboard',
				'name'  => __( 'Dashboard', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-admin-generic',
				'name'  => __( 'Generic', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-filter',
				'name'  => __( 'Filter', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-admin-home',
				'name'  => __( 'Home', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-admin-media',
				'name'  => __( 'Media', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-menu',
				'name'  => __( 'Menu', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-admin-multisite',
				'name'  => __( 'Multisite', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-admin-network',
				'name'  => __( 'Network', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-admin-page',
				'name'  => __( 'Page', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-admin-plugins',
				'name'  => __( 'Plugins', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-admin-settings',
				'name'  => __( 'Settings', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-admin-site',
				'name'  => __( 'Site', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-admin-tools',
				'name'  => __( 'Tools', 'icon-picker' ),
			),
			array(
				'group' => 'admin',
				'id'    => 'dashicons-admin-users',
				'name'  => __( 'Users', 'icon-picker' ),
			),
			array(
				'group' => 'post-formats',
				'id'    => 'dashicons-format-standard',
				'name'  => __( 'Standard', 'icon-picker' ),
			),
			array(
				'group' => 'post-formats',
				'id'    => 'dashicons-format-aside',
				'name'  => __( 'Aside', 'icon-picker' ),
			),
			array(
				'group' => 'post-formats',
				'id'    => 'dashicons-format-image',
				'name'  => __( 'Image', 'icon-picker' ),
			),
			array(
				'group' => 'post-formats',
				'id'    => 'dashicons-format-video',
				'name'  => __( 'Video', 'icon-picker' ),
			),
			array(
				'group' => 'post-formats',
				'id'    => 'dashicons-format-audio',
				'name'  => __( 'Audio', 'icon-picker' ),
			),
			array(
				'group' => 'post-formats',
				'id'    => 'dashicons-format-quote',
				'name'  => __( 'Quote', 'icon-picker' ),
			),
			array(
				'group' => 'post-formats',
				'id'    => 'dashicons-format-gallery',
				'name'  => __( 'Gallery', 'icon-picker' ),
			),
			array(
				'group' => 'post-formats',
				'id'    => 'dashicons-format-links',
				'name'  => __( 'Links', 'icon-picker' ),
			),
			array(
				'group' => 'post-formats',
				'id'    => 'dashicons-format-status',
				'name'  => __( 'Status', 'icon-picker' ),
			),
			array(
				'group' => 'post-formats',
				'id'    => 'dashicons-format-chat',
				'name'  => __( 'Chat', 'icon-picker' ),
			),
			array(
				'group' => 'welcome-screen',
				'id'    => 'dashicons-welcome-add-page',
				'name'  => __( 'Add page', 'icon-picker' ),
			),
			array(
				'group' => 'welcome-screen',
				'id'    => 'dashicons-welcome-comments',
				'name'  => __( 'Comments', 'icon-picker' ),
			),
			array(
				'group' => 'welcome-screen',
				'id'    => 'dashicons-welcome-edit-page',
				'name'  => __( 'Edit page', 'icon-picker' ),
			),
			array(
				'group' => 'welcome-screen',
				'id'    => 'dashicons-welcome-learn-more',
				'name'  => __( 'Learn More', 'icon-picker' ),
			),
			array(
				'group' => 'welcome-screen',
				'id'    => 'dashicons-welcome-view-site',
				'name'  => __( 'View Site', 'icon-picker' ),
			),
			array(
				'group' => 'welcome-screen',
				'id'    => 'dashicons-welcome-widgets-menus',
				'name'  => __( 'Widgets', 'icon-picker' ),
			),
			array(
				'group' => 'welcome-screen',
				'id'    => 'dashicons-welcome-write-blog',
				'name'  => __( 'Write Blog', 'icon-picker' ),
			),
			array(
				'group' => 'image-editor',
				'id'    => 'dashicons-image-crop',
				'name'  => __( 'Crop', 'icon-picker' ),
			),
			array(
				'group' => 'image-editor',
				'id'    => 'dashicons-image-filter',
				'name'  => __( 'Filter', 'icon-picker' ),
			),
			array(
				'group' => 'image-editor',
				'id'    => 'dashicons-image-rotate',
				'name'  => __( 'Rotate', 'icon-picker' ),
			),
			array(
				'group' => 'image-editor',
				'id'    => 'dashicons-image-rotate-left',
				'name'  => __( 'Rotate Left', 'icon-picker' ),
			),
			array(
				'group' => 'image-editor',
				'id'    => 'dashicons-image-rotate-right',
				'name'  => __( 'Rotate Right', 'icon-picker' ),
			),
			array(
				'group' => 'image-editor',
				'id'    => 'dashicons-image-flip-vertical',
				'name'  => __( 'Flip Vertical', 'icon-picker' ),
			),
			array(
				'group' => 'image-editor',
				'id'    => 'dashicons-image-flip-horizontal',
				'name'  => __( 'Flip Horizontal', 'icon-picker' ),
			),
			array(
				'group' => 'image-editor',
				'id'    => 'dashicons-undo',
				'name'  => __( 'Undo', 'icon-picker' ),
			),
			array(
				'group' => 'image-editor',
				'id'    => 'dashicons-redo',
				'name'  => __( 'Redo', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-bold',
				'name'  => __( 'Bold', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-italic',
				'name'  => __( 'Italic', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-ul',
				'name'  => __( 'Unordered List', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-ol',
				'name'  => __( 'Ordered List', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-quote',
				'name'  => __( 'Quote', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-alignleft',
				'name'  => __( 'Align Left', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-aligncenter',
				'name'  => __( 'Align Center', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-alignright',
				'name'  => __( 'Align Right', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-insertmore',
				'name'  => __( 'Insert More', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-spellcheck',
				'name'  => __( 'Spell Check', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-distractionfree',
				'name'  => __( 'Distraction-free', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-kitchensink',
				'name'  => __( 'Kitchensink', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-underline',
				'name'  => __( 'Underline', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-justify',
				'name'  => __( 'Justify', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-textcolor',
				'name'  => __( 'Text Color', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-paste-word',
				'name'  => __( 'Paste Word', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-paste-text',
				'name'  => __( 'Paste Text', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-removeformatting',
				'name'  => __( 'Clear Formatting', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-video',
				'name'  => __( 'Video', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-customchar',
				'name'  => __( 'Custom Characters', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-indent',
				'name'  => __( 'Indent', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-outdent',
				'name'  => __( 'Outdent', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-help',
				'name'  => __( 'Help', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-strikethrough',
				'name'  => __( 'Strikethrough', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-unlink',
				'name'  => __( 'Unlink', 'icon-picker' ),
			),
			array(
				'group' => 'text-editor',
				'id'    => 'dashicons-editor-rtl',
				'name'  => __( 'RTL', 'icon-picker' ),
			),
			array(
				'group' => 'post',
				'id'    => 'dashicons-align-left',
				'name'  => __( 'Align Left', 'icon-picker' ),
			),
			array(
				'group' => 'post',
				'id'    => 'dashicons-align-right',
				'name'  => __( 'Align Right', 'icon-picker' ),
			),
			array(
				'group' => 'post',
				'id'    => 'dashicons-align-center',
				'name'  => __( 'Align Center', 'icon-picker' ),
			),
			array(
				'group' => 'post',
				'id'    => 'dashicons-align-none',
				'name'  => __( 'Align None', 'icon-picker' ),
			),
			array(
				'group' => 'post',
				'id'    => 'dashicons-lock',
				'name'  => __( 'Lock', 'icon-picker' ),
			),
			array(
				'group' => 'post',
				'id'    => 'dashicons-calendar',
				'name'  => __( 'Calendar', 'icon-picker' ),
			),
			array(
				'group' => 'post',
				'id'    => 'dashicons-calendar-alt',
				'name'  => __( 'Calendar', 'icon-picker' ),
			),
			array(
				'group' => 'post',
				'id'    => 'dashicons-hidden',
				'name'  => __( 'Hidden', 'icon-picker' ),
			),
			array(
				'group' => 'post',
				'id'    => 'dashicons-visibility',
				'name'  => __( 'Visibility', 'icon-picker' ),
			),
			array(
				'group' => 'post',
				'id'    => 'dashicons-post-status',
				'name'  => __( 'Post Status', 'icon-picker' ),
			),
			array(
				'group' => 'post',
				'id'    => 'dashicons-post-trash',
				'name'  => __( 'Post Trash', 'icon-picker' ),
			),
			array(
				'group' => 'post',
				'id'    => 'dashicons-edit',
				'name'  => __( 'Edit', 'icon-picker' ),
			),
			array(
				'group' => 'post',
				'id'    => 'dashicons-trash',
				'name'  => __( 'Trash', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-arrow-up',
				'name'  => __( 'Arrow: Up', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-arrow-down',
				'name'  => __( 'Arrow: Down', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-arrow-left',
				'name'  => __( 'Arrow: Left', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-arrow-right',
				'name'  => __( 'Arrow: Right', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-arrow-up-alt',
				'name'  => __( 'Arrow: Up', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-arrow-down-alt',
				'name'  => __( 'Arrow: Down', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-arrow-left-alt',
				'name'  => __( 'Arrow: Left', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-arrow-right-alt',
				'name'  => __( 'Arrow: Right', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-arrow-up-alt2',
				'name'  => __( 'Arrow: Up', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-arrow-down-alt2',
				'name'  => __( 'Arrow: Down', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-arrow-left-alt2',
				'name'  => __( 'Arrow: Left', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-arrow-right-alt2',
				'name'  => __( 'Arrow: Right', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-leftright',
				'name'  => __( 'Left-Right', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-sort',
				'name'  => __( 'Sort', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-list-view',
				'name'  => __( 'List View', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-exerpt-view',
				'name'  => __( 'Excerpt View', 'icon-picker' ),
			),
			array(
				'group' => 'sorting',
				'id'    => 'dashicons-grid-view',
				'name'  => __( 'Grid View', 'icon-picker' ),
			),
			array(
				'group' => 'social',
				'id'    => 'dashicons-share',
				'name'  => __( 'Share', 'icon-picker' ),
			),
			array(
				'group' => 'social',
				'id'    => 'dashicons-share-alt',
				'name'  => __( 'Share', 'icon-picker' ),
			),
			array(
				'group' => 'social',
				'id'    => 'dashicons-share-alt2',
				'name'  => __( 'Share', 'icon-picker' ),
			),
			array(
				'group' => 'social',
				'id'    => 'dashicons-twitter',
				'name'  => __( 'Twitter', 'icon-picker' ),
			),
			array(
				'group' => 'social',
				'id'    => 'dashicons-rss',
				'name'  => __( 'RSS', 'icon-picker' ),
			),
			array(
				'group' => 'social',
				'id'    => 'dashicons-email',
				'name'  => __( 'Email', 'icon-picker' ),
			),
			array(
				'group' => 'social',
				'id'    => 'dashicons-email-alt',
				'name'  => __( 'Email', 'icon-picker' ),
			),
			array(
				'group' => 'social',
				'id'    => 'dashicons-facebook',
				'name'  => __( 'Facebook', 'icon-picker' ),
			),
			array(
				'group' => 'social',
				'id'    => 'dashicons-facebook-alt',
				'name'  => __( 'Facebook', 'icon-picker' ),
			),
			array(
				'group' => 'social',
				'id'    => 'dashicons-googleplus',
				'name'  => __( 'Google+', 'icon-picker' ),
			),
			array(
				'group' => 'social',
				'id'    => 'dashicons-networking',
				'name'  => __( 'Networking', 'icon-picker' ),
			),
			array(
				'group' => 'jobs',
				'id'    => 'dashicons-art',
				'name'  => __( 'Art', 'icon-picker' ),
			),
			array(
				'group' => 'jobs',
				'id'    => 'dashicons-hammer',
				'name'  => __( 'Hammer', 'icon-picker' ),
			),
			array(
				'group' => 'jobs',
				'id'    => 'dashicons-migrate',
				'name'  => __( 'Migrate', 'icon-picker' ),
			),
			array(
				'group' => 'jobs',
				'id'    => 'dashicons-performance',
				'name'  => __( 'Performance', 'icon-picker' ),
			),
			array(
				'group' => 'products',
				'id'    => 'dashicons-wordpress',
				'name'  => __( 'WordPress', 'icon-picker' ),
			),
			array(
				'group' => 'products',
				'id'    => 'dashicons-wordpress-alt',
				'name'  => __( 'WordPress', 'icon-picker' ),
			),
			array(
				'group' => 'products',
				'id'    => 'dashicons-pressthis',
				'name'  => __( 'PressThis', 'icon-picker' ),
			),
			array(
				'group' => 'products',
				'id'    => 'dashicons-update',
				'name'  => __( 'Update', 'icon-picker' ),
			),
			array(
				'group' => 'products',
				'id'    => 'dashicons-screenoptions',
				'name'  => __( 'Screen Options', 'icon-picker' ),
			),
			array(
				'group' => 'products',
				'id'    => 'dashicons-info',
				'name'  => __( 'Info', 'icon-picker' ),
			),
			array(
				'group' => 'products',
				'id'    => 'dashicons-cart',
				'name'  => __( 'Cart', 'icon-picker' ),
			),
			array(
				'group' => 'products',
				'id'    => 'dashicons-feedback',
				'name'  => __( 'Feedback', 'icon-picker' ),
			),
			array(
				'group' => 'products',
				'id'    => 'dashicons-cloud',
				'name'  => __( 'Cloud', 'icon-picker' ),
			),
			array(
				'group' => 'products',
				'id'    => 'dashicons-translation',
				'name'  => __( 'Translation', 'icon-picker' ),
			),
			array(
				'group' => 'taxonomies',
				'id'    => 'dashicons-tag',
				'name'  => __( 'Tag', 'icon-picker' ),
			),
			array(
				'group' => 'taxonomies',
				'id'    => 'dashicons-category',
				'name'  => __( 'Category', 'icon-picker' ),
			),
			array(
				'group' => 'alerts',
				'id'    => 'dashicons-yes',
				'name'  => __( 'Yes', 'icon-picker' ),
			),
			array(
				'group' => 'alerts',
				'id'    => 'dashicons-no',
				'name'  => __( 'No', 'icon-picker' ),
			),
			array(
				'group' => 'alerts',
				'id'    => 'dashicons-no-alt',
				'name'  => __( 'No', 'icon-picker' ),
			),
			array(
				'group' => 'alerts',
				'id'    => 'dashicons-plus',
				'name'  => __( 'Plus', 'icon-picker' ),
			),
			array(
				'group' => 'alerts',
				'id'    => 'dashicons-minus',
				'name'  => __( 'Minus', 'icon-picker' ),
			),
			array(
				'group' => 'alerts',
				'id'    => 'dashicons-dismiss',
				'name'  => __( 'Dismiss', 'icon-picker' ),
			),
			array(
				'group' => 'alerts',
				'id'    => 'dashicons-marker',
				'name'  => __( 'Marker', 'icon-picker' ),
			),
			array(
				'group' => 'alerts',
				'id'    => 'dashicons-star-filled',
				'name'  => __( 'Star: Filled', 'icon-picker' ),
			),
			array(
				'group' => 'alerts',
				'id'    => 'dashicons-star-half',
				'name'  => __( 'Star: Half', 'icon-picker' ),
			),
			array(
				'group' => 'alerts',
				'id'    => 'dashicons-star-empty',
				'name'  => __( 'Star: Empty', 'icon-picker' ),
			),
			array(
				'group' => 'alerts',
				'id'    => 'dashicons-flag',
				'name'  => __( 'Flag', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-controls-skipback',
				'name'  => __( 'Skip Back', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-controls-back',
				'name'  => __( 'Back', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-controls-play',
				'name'  => __( 'Play', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-controls-pause',
				'name'  => __( 'Pause', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-controls-forward',
				'name'  => __( 'Forward', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-controls-skipforward',
				'name'  => __( 'Skip Forward', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-controls-repeat',
				'name'  => __( 'Repeat', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-controls-volumeon',
				'name'  => __( 'Volume: On', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-controls-volumeoff',
				'name'  => __( 'Volume: Off', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-media-archive',
				'name'  => __( 'Archive', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-media-audio',
				'name'  => __( 'Audio', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-media-code',
				'name'  => __( 'Code', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-media-default',
				'name'  => __( 'Default', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-media-document',
				'name'  => __( 'Document', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-media-interactive',
				'name'  => __( 'Interactive', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-media-spreadsheet',
				'name'  => __( 'Spreadsheet', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-media-text',
				'name'  => __( 'Text', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-media-video',
				'name'  => __( 'Video', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-playlist-audio',
				'name'  => __( 'Audio Playlist', 'icon-picker' ),
			),
			array(
				'group' => 'media',
				'id'    => 'dashicons-playlist-video',
				'name'  => __( 'Video Playlist', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-album',
				'name'  => __( 'Album', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-analytics',
				'name'  => __( 'Analytics', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-awards',
				'name'  => __( 'Awards', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-backup',
				'name'  => __( 'Backup', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-building',
				'name'  => __( 'Building', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-businessman',
				'name'  => __( 'Businessman', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-camera',
				'name'  => __( 'Camera', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-carrot',
				'name'  => __( 'Carrot', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-chart-pie',
				'name'  => __( 'Chart: Pie', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-chart-bar',
				'name'  => __( 'Chart: Bar', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-chart-line',
				'name'  => __( 'Chart: Line', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-chart-area',
				'name'  => __( 'Chart: Area', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-desktop',
				'name'  => __( 'Desktop', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-forms',
				'name'  => __( 'Forms', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-groups',
				'name'  => __( 'Groups', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-id',
				'name'  => __( 'ID', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-id-alt',
				'name'  => __( 'ID', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-images-alt',
				'name'  => __( 'Images', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-images-alt2',
				'name'  => __( 'Images', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-index-card',
				'name'  => __( 'Index Card', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-layout',
				'name'  => __( 'Layout', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-location',
				'name'  => __( 'Location', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-location-alt',
				'name'  => __( 'Location', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-products',
				'name'  => __( 'Products', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-portfolio',
				'name'  => __( 'Portfolio', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-book',
				'name'  => __( 'Book', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-book-alt',
				'name'  => __( 'Book', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-download',
				'name'  => __( 'Download', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-upload',
				'name'  => __( 'Upload', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-clock',
				'name'  => __( 'Clock', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-lightbulb',
				'name'  => __( 'Lightbulb', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-money',
				'name'  => __( 'Money', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-palmtree',
				'name'  => __( 'Palm Tree', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-phone',
				'name'  => __( 'Phone', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-search',
				'name'  => __( 'Search', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-shield',
				'name'  => __( 'Shield', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-shield-alt',
				'name'  => __( 'Shield', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-slides',
				'name'  => __( 'Slides', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-smartphone',
				'name'  => __( 'Smartphone', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-smiley',
				'name'  => __( 'Smiley', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-sos',
				'name'  => __( 'S.O.S.', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-sticky',
				'name'  => __( 'Sticky', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-store',
				'name'  => __( 'Store', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-tablet',
				'name'  => __( 'Tablet', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-testimonial',
				'name'  => __( 'Testimonial', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-tickets-alt',
				'name'  => __( 'Tickets', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-thumbs-up',
				'name'  => __( 'Thumbs Up', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-thumbs-down',
				'name'  => __( 'Thumbs Down', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-unlock',
				'name'  => __( 'Unlock', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-vault',
				'name'  => __( 'Vault', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-video-alt',
				'name'  => __( 'Video', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-video-alt2',
				'name'  => __( 'Video', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-video-alt3',
				'name'  => __( 'Video', 'icon-picker' ),
			),
			array(
				'group' => 'misc',
				'id'    => 'dashicons-warning',
				'name'  => __( 'Warning', 'icon-picker' ),
			),
		);

		$dashicons = array();
		foreach ( $items as $icon ) {
			$dashicons[ $icon['id'] ] = $icon['id'];
		}
		return $dashicons;
	}
}
