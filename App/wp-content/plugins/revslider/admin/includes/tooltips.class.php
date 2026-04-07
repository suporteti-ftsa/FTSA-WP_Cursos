<?php
/**
* @author    ThemePunch <info@themepunch.com>
* @link      https://www.themepunch.com/
* @copyright 2024 ThemePunch
*/

if(!defined('ABSPATH')) exit();

class RevSliderTooltips {
	
	public function get_tooltip_by_handle($handle){
		$tt = self::getTooltips();
		foreach($tt as $_tt){
			if(isset($_tt[$handle])) return [$handle => $_tt[$handle]];
		}

		return false;
	}

	public function get_tooltips_by_string($search){
		$tt		= self::getTooltips();
		$found	= [];
		$search = strtolower($search);
		foreach($tt as $_tt){
			foreach($_tt as $handle => $v){
				$_v = strtolower(json_encode($v));
				if(strpos($_v, $search) === false) continue;
				$found[$handle] = $v;
			}
		}

		return $found;
	}

	public static function getTooltips() {
		return apply_filters('revslider_getTooltips', [
			'module' => [				
				'diffbulkvalues' => [					
					'desc' => 'Bulk edit target attributes differ.<br>Using values from the first layer.',					
				],
				'fullwidth' => [					
					'desc' => 'The module will always match the width of the browser window.
					',					
				],
				'fullheight' => [					
					'desc' => 'The module height will match the browser window (reduced by custom limitations) or expand beyond it if the content requires.
					',					
				],
				'decreasemoduleheight' => [					
					'desc' => 'The module height will be reduced by the height of one or more containers, defined using HTML selectors or pixel values. Separate multiple selectors with commas.
					',
				],
				'minmaxmodulesizes' => [					
					'desc' => 'Set minimum and maximum height for the module. Additionally, if the module is not using full width mode, set maximum width.
					',
				],
				'keepbreakpoint' => [					
					'desc' => 'When enabled, the module’s height remains fixed between breakpoints. At the next breakpoint, the height updates to match the content flow container height defined for that device size.
					',
				],
				'preventpagescroll' => [					
					'desc' => 'Locks the module in place on screen while the scroll-driven timeline plays within the range specified below.<br><br>
					The module remains fixed until the timeline moves past the "Hold From" and "Hold To" positions.<br><br>
					If advanced mode is active, the module scrolls gently between the top and bottom of the page for subtle dynamic movement.
					',
				],
				'keepratio' => [					
					'desc' => 'Between breakpoints, the module maintains its aspect ratio by scaling height proportionately with width.<br><br>
					At the next breakpoint, it adopts the aspect ratio defined in the content flow container for that device size.
					',					
				],
				'ignoremobileheight' => [					
					'desc' => 'Ignore height changes caused by the visibility of the mobile browser’s address bar.',					
				],
				'acc_gen_aria' => [
					'desc' => 'Adds custom and auto ARIA roles and labels to improve screen reader compatibility. Update values per layer and slide as required.
					',					
				],
				'acc_gen_arialive' => [
					'desc' => 'Announces active slide changes to screen readers via aria-live. Use this only if slide content needs dynamic announcement, and set the announcement text per slide as required.
					',					
				],
				'youtubetip' => [					
					'desc' => 'The “YouTube Stream” content source is used to display a full stream of videos from a channel/playlist. If you want to display a single youtube video, please select the content source “Default Slider” and add a video layer in the slide editor.',					
				],
				'vimeotip' => [					
					'desc' => 'The “Vimeo Stream” content source is used to display a full stream of videos from a user/album/group/channel. If you want to display a single vimeo video, please select the content source “Default Slider” and add a video layer in the slide editor.',					
				],
				'layerupscaling' => [					
					'desc' => 'Layer size will automatically increase to exceed the predefined dimensions on screens larger than your maximum custom-defined device size.',					
				],
				'keepflow' => [					
					'desc' => 'Sets the container width to auto and uses the Content Flow width (per device) as the limit.',
				],
				'infinitycarousel' => [					
					'desc' => 'When enabled, the carousel automatically adjusts the combined width or height of slides to exceed the visible area, allowing for seamless infinite scrolling.
					',					
				],
				'keepcarouselratio' => [					
					'desc' => 'Adjusts the width of individual slides based on the module’s height and aspect ratio to create a justified layout.
					',					
				],

				'stretchcarousel' => [					
					'desc' => 'Expands slides to fill the full width of the module, showing only one slide at a time in the visible area.
					',					
				],
				'globallayersvisibility' => [					
					'desc' => 'Toggles the visibility of static slide layers when a non-static slide is selected in the editor.',					
				],
				'carouselsiblings' => [					
					'desc' => 'Adjusts the distance, animation, and styling of non-focused carousel slides.<br><br>
					Note: Some effects (e.g., 3D Spin) only appear during transitions in preview or on the live site. The editor may show slight differences in spacing and sibling count.',					
				],
				'warning_global_px_scroll' => [					
					'desc' => 'On Scroll is DEACTIVATED under Settings > Scroll & Parallax.',					
				],
				'warning_global_px_mouse' => [					
					'desc' => 'On Mouse is DEACTIVATED  under Settings > Scroll & Parallax.',					
				],
				'aiupscaleimgsrc' => [					
					'desc' => 'Select an image from the WordPress library, Slider Revolution Elements library, from a layer, or generate with AI.',
				],
			],
			'slide' => [	
							
			],
			'global' => [	
				'gdprsettings' => [
					'desc' => 'Controls how YouTube and Vimeo videos are loaded depending on the active consent management plugin.<br><br>If "none", videos always allowed to play.<br><br>If active, consent is checked in the browser before loading the video.<br><br>While the visitor is deciding, loading waits up to 15 seconds. If no consent is given, the video stays blocked until the next page load.',
				],			
			],
			'layer' => [	
				'frames_custom_off' => [
					'desc' => 'Split attribute target<br>onto its own track'
				],
				'frames_custom_on' => [
					'desc' => 'Merge attribute back<br>into the parent track'
				],
				'loop_between_frames' => [
					'desc' => 'Loop animation in<br>between keyframes.'
				]				
			],
			'navigation' => [
                    'navthumbnail' => [
						'desc' => 'Optimization will be used only when new Image Picked. It is stored in the selected Format and will be used as Navigation Thumbnail.',
					]					
			],
			'blocksettingswrapperid' => [
				'desc' => 'Enter a word or two — without spaces or special characters — to make a unique web address just for this module.'
			]
		]);
	}

	/*
	OLD DATA, leave for now as we might use it
			'help_mode' => [
			
				'title' => 'Help Mode',
				'target' => '.help_wrap',
				'alignment' => 'bottom',
				'margin' => '20px 0 0 0',
				'text' => 'Get information about the different options available for your Slider',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/help-center/?utm_source=admin&utm_medium=button&utm_campaign=srusers&utm_content=faq'

			],
			'slides' => [
			
				'title' => 'Add Slide',
				'target' => '#add_slide_toolbar_wrap',
				'alignment' => 'bottom',
				'margin' => '20px 0 0 0',
				'text' => "Add new Slides, reorder your current Slides and manage the Module's global content",
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/slide-management/'

			],
			'add_layer' => [
			
				'title' => 'Add Layer',
				'target' => '#add_layer_toolbar_wrap',
				'alignment' => 'bottom',
				'margin' => '20px 0 0 0',
				'text' => 'Add new content to the currently active Slide'

			],
			'tooltip_button' => [
			
				'title' => 'Tooltip Button',
				'target' => '.tooltip_wrap',
				'alignment' => 'bottom',
				'margin' => '20px 0 0 0',
				'text' => 'Enable the tooltip wizard for a quick overview of the editor'

			],
			'undo_redo' => [
			
				'title' => 'Undo Redo',
				'target' => '.undo_redo_wrap',
				'alignment' => 'bottom',
				'margin' => '20px 0 0 0',
				'text' => "Undo or redo changes you've made while using the editor"

			],
			'quick_style' => [
			
				'title' => 'Quick Style Layer',
				'target' => '#quick_style_trigger',
				'alignment' => 'bottom',
				'margin' => '20px 0 0 0',
				'text' => 'Add pre-styled headlines, paragraph text and buttons to the current Slide'

			],
			'device_switcher' => [
			
				'title' => 'Device View Switcher',
				'target' => '#main_screenselector',
				'alignment' => 'bottom',
				'margin' => '20px 0 0 0',
				'trigger' => '#main_screenselector:visible',
				'text' => "Adjust your content's size and position for different screen sizes"

			],
			'layer_selections' => [
			
				'title' => 'Layer Selections',
				'target' => '#toolkit_selector_wrap',
				'alignment' => 'bottom',
				'margin' => '20px 0 0 0',
				'text' => 'Choose the way you would like to select multiple Layers on the stage'

			],
			'slider_settings' => [
			
				'title' => 'Slider Settings',
				'target' => '#module_settings_trigger',
				'alignment' => 'bottom',
				'margin' => '20px 0 0 0',
				'elementcss' => 'width: 80px',
				'focus' => 'none',
				'trigger' => ['#module_settings_trigger'],
				'text' => "Adjust the module's Layout and set its Slideshow behavior"

			],
			'slider_navigation' => [
			
				'title' => 'Slider Navigation',
				'target' => '#module_navigation_trigger',
				'alignment' => 'bottom',
				'margin' => '20px 0 0 0',
				'elementcss' => 'width: 80px',
				'focus' => 'none',
				'trigger' => ['#module_navigation_trigger'],
				'text' => 'Add a variety of navigation elements to your Slider'

			],
			'slide_settings' => [
			
				'title' => 'Slide Settings',
				'target' => '#module_slide_trigger',
				'alignment' => 'bottom-left',
				'margin' => '20px 0 0 78px',
				'elementcss' => 'width: 80px',
				'focus' => 'none',
				'trigger' => ['#module_slide_trigger'],
				'text' => "Set the Slide's main background and slide-change animation"

			],
			'layer_settings' => [
			
				'title' => 'Layer Settings',
				'target' => '#module_layers_trigger',
				'alignment' => 'bottom-left',
				'margin' => '20px 0 0 78px',
				'elementcss' => 'width: 80px',
				'focus' => 'none',
				'trigger' => ['#module_layers_trigger'],
				'text' => "Adjust the size and position for your Slide's content"

			],
			'shortcode' => [
			
				'title' => 'Title/Shortcode',
				'target' => '#sr_shortcode',
				'placer' => '#rs_shortcode_label',
				'alignment' => 'left',
				'margin' => '-4px 0 0 -17px',
				'trigger' => ['#module_settings_trigger', '#gst_sl_1'],
				'section' => 'Module General Options -> Title',
				'text' => 'The shortcode for the module is located here.'

			],
			'back' => [
			
				'title' => 'Back to Plugin Admin Page',
				'target' => '#back_to_overview',
				'alignment' => 'bottom-right',
				'margin' => '20px 0 0 -90px',
				'text' => "Click here to go back to the plugin's main admin page"

			],
			'add_slide' => [
			
				'title' => 'Add Slide',
				'target' => '#add_slide_toolbar_wrap',
				'focus' => '.toolbar_dd_subdrop_wrap',
				'alignment' => 'top-right',
				'margin' => '-5px 0 0 20px',
				'cssClass' => 'tip-hover',
				'text' => 'Add a new Slide to the Slider',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/slide-management/'

			],
			'global_layers' => [
			
				'title' => 'Global Layers',
				'target' => '#add_slide_toolbar_wrap',
				'focus' => '.static-slide-btn',
				'alignment' => 'top-right',
				'margin' => '-5px 0 0 20px',
				'cssClass' => 'tip-hover tip-hover-staticlayers',
				'text' => 'Content that should always be visible throughout the life-cycle of your Slider exists here',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/global-layers/'

			],
			'slide_order' => [
			
				'title' => 'Change Slide Order',
				'target' => '#add_slide_toolbar_wrap',
				'focus' => '.slide_list_element.selected',
				'alignment' => 'top-right',
				'margin' => '-5px 0 0 20px',
				'cssClass' => 'tip-hover tip-hover-slideorder',
				'text' => 'Drag these menu items on top of one another to change the order of your Slides',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/slide-management/#switch-reorder-slides'

			],
			'add_layer_text' => [
			
				'title' => 'Add Text Layer',
				'target' => '#add_layer_toolbar_wrap',
				'focus' => '#toolbar_add_layer_text',
				'alignment' => 'top-right',
				'margin' => '-5px 0 0 20px',
				'cssClass' => 'tip-hover tip-hover-text',
				'text' => 'Add a text element to the current Slide',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/layer-content/'

			],
			'add_layer_image' => [
			
				'title' => 'Add Image Layer',
				'target' => '#add_layer_toolbar_wrap',
				'focus' => '#toolbar_add_layer_image',
				'alignment' => 'top-right',
				'margin' => '-5px 0 0 20px',
				'cssClass' => 'tip-hover tip-hover-image',
				'text' => 'Add an image to the current Slide',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/layer-content/'

			],
			'add_layer_button' => [
			
				'title' => 'Add Button Layer',
				'target' => '#add_layer_toolbar_wrap',
				'focus' => '#toolbar_add_layer_button',
				'alignment' => 'top-right',
				'margin' => '-5px 0 0 20px',
				'cssClass' => 'tip-hover tip-hover-button',
				'text' => 'Add a pre-styled button to the current Slide',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/layer-content/'

			],
			'add_layer_shape' => [
			
				'title' => 'Add Shape Layer',
				'target' => '#add_layer_toolbar_wrap',
				'focus' => '#toolbar_add_layer_shape',
				'alignment' => 'top-right',
				'margin' => '-5px 0 0 20px',
				'cssClass' => 'tip-hover tip-hover-shape',
				'text' => 'Shapes are elements with a background colors but not content',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/layer-content/'

			],
			'add_layer_video' => [
			
				'title' => 'Add Video Layer',
				'target' => '#add_layer_toolbar_wrap',
				'focus' => '#toolbar_add_layer_video',
				'alignment' => 'top-right',
				'margin' => '-5px 0 0 20px',
				'cssClass' => 'tip-hover tip-hover-video',
				'text' => 'Add a YouTube, Vimeo or HTML5 video to the current Slide',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/layer-content/'

			],
			'add_layer_audio' => [
			
				'title' => 'Add Audio Layer',
				'target' => '#add_layer_toolbar_wrap',
				'focus' => '#toolbar_add_layer_audio',
				'alignment' => 'top-right',
				'margin' => '-5px 0 0 20px',
				'cssClass' => 'tip-hover tip-hover-audio',
				'text' => 'Add sound to the current Slide',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/layer-content/'

			],
			'add_layer_object' => [
			
				'title' => 'Add Object Layer',
				'target' => '#add_layer_toolbar_wrap',
				'focus' => '#toolbar_add_layer_object',
				'alignment' => 'top-right',
				'margin' => '-5px 0 0 20px',
				'cssClass' => 'tip-hover tip-hover-object',
				'text' => 'Add a simple icon or SVG element to the current Slide',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/layer-content/'

			],
			'add_layer_row' => [
			
				'title' => 'Add Row',
				'target' => '#add_layer_toolbar_wrap',
				'focus' => '#toolbar_add_layer_row',
				'alignment' => 'top-right',
				'margin' => '-5px 0 0 20px',
				'cssClass' => 'tip-hover tip-hover-row',
				'text' => 'Add a new Row to the current Slide to allow for Rows/Column-based content',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/rows-columns/'

			],
			'add_layer_group' => [
			
				'title' => 'Add Group',
				'target' => '#add_layer_toolbar_wrap',
				'focus' => '#toolbar_add_layer_group',
				'alignment' => 'top-right',
				'margin' => '-5px 0 0 20px',
				'cssClass' => 'tip-hover tip-hover-group',
				'text' => 'Add a special container to the Slide that can then include multiple Layers grouped together',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/layer-groups/'

			],
			'add_layer_layerlibrary' => [
			
				'title' => 'Layer Library',
				'target' => '#add_layer_toolbar_wrap',
				'focus' => '#add_from_layerlibrary',
				'alignment' => 'top-right',
				'margin' => '-5px 0 0 20px',
				'cssClass' => 'tip-hover tip-hover-layerlibrary',
				'text' => 'Add a text-based template that includes a predefined style and animation',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/layer-content/'

			],
			'add_layer_importlayer' => [
			
				'title' => 'Import Layer',
				'target' => '#add_layer_toolbar_wrap',
				'focus' => '#import_layers',
				'alignment' => 'top-right',
				'margin' => '-5px 0 0 20px',
				'cssClass' => 'tip-hover tip-hover-importlayer',
				'text' => 'Import a Layer from another Slider or Slide',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/layer-content/'

			],
			'delete_layer' => [
			
				'title' => 'Delete Layer',
				'target' => '#do_delete_layer',
				'alignment' => 'bottom',
				'margin' => '20px 0 0 0',
				'trigger' => ['#module_layers_trigger'],
				'text' => 'Delete the currently selected Layer',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/editor-overview/'

			],
			'duplicate_layer' => [
			
				'title' => 'Duplicate Layer',
				'target' => '#duplicate_btn_icon',
				'focus' => '#do_duplicate_layer',
				'alignment' => 'top-left',
				'margin' => '-5px 0 0 -20px',
				'trigger' => ['#module_layers_trigger'],
				'cssClass' => 'tip-hover tip-hover-duplicate',
				'text' => 'Duplicate the currently selected Layer',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/editor-overview/'

			],
			'copy_layer' => [
			
				'title' => 'Copy Layer',
				'target' => '#duplicate_btn_icon',
				'focus' => '#do_copy_layer',
				'alignment' => 'top-left',
				'margin' => '-5px 0 0 -20px',
				'trigger' => ['#module_layers_trigger'],
				'cssClass' => 'tip-hover tip-hover-copy',
				'text' => 'Copy the current Layer and paste it into another Slide',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/editor-overview/'

			],
			'paste_layer' => [
			
				'title' => 'Paste Layer',
				'target' => '#duplicate_btn_icon',
				'focus' => '#do_paste_layer',
				'alignment' => 'top-left',
				'margin' => '-5px 0 0 -20px',
				'trigger' => ['#module_layers_trigger'],
				'cssClass' => 'tip-hover tip-hover-paste',
				'text' => 'Paste a copied Layer into the current Slide',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/editor-overview/'

			],
			'lock_layers' => [
			
				'title' => 'Lock Layers',
				'target' => '#do_lock_layer',
				'focus' => '#toggle_lock_layer',
				'alignment' => 'top-left',
				'margin' => '-5px 0 0 -20px',
				'trigger' => ['#module_layers_trigger'],
				'cssClass' => 'tip-hover tip-hover-lock',
				'text' => 'Lock the currently selected Layer(s) from being edited',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/editor-overview/'

			],
			'unlock_layers' => [
			
				'title' => 'Unlock Layers',
				'target' => '#do_lock_layer',
				'focus' => '#unlock_all_layer',
				'alignment' => 'top-left',
				'margin' => '-5px 0 0 -20px',
				'trigger' => ['#module_layers_trigger'],
				'cssClass' => 'tip-hover tip-hover-unlock',
				'text' => 'Unlock the currently selected Layers so they can be edited',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/editor-overview/'

			],
			'hide_highlight_boxes' => [
			
				'title' => 'Hide Highlight Boxes',
				'target' => '#do_show_layer',
				'focus' => '#hide_highlight_boxes',
				'alignment' => 'top-left',
				'margin' => '-5px 0 0 -20px',
				'trigger' => ['#module_layers_trigger'],
				'cssClass' => 'tip-hover tip-hover-highlightboxes',
				'text' => "Hide the editor's outline guide markers",
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/editor-overview/'

			],
			'show_hide_selected' => [
			
				'title' => 'Show/Hide Selected',
				'target' => '#do_show_layer',
				'focus' => '#toggle_visible_layer',
				'alignment' => 'top-left',
				'margin' => '-5px 0 0 -20px',
				'trigger' => ['#module_layers_trigger'],
				'cssClass' => 'tip-hover tip-hover-showhide',
				'text' => "Show the editor's outline guide markers for the selected Layers",
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/editor-overview/'

			],
			'set_all_visible' => [
			
				'title' => 'Set All Visible',
				'target' => '#do_show_layer',
				'focus' => '#visible_all_layer',
				'alignment' => 'top-left',
				'margin' => '-5px 0 0 -20px',
				'trigger' => ['#module_layers_trigger'],
				'cssClass' => 'tip-hover tip-hover-setallvisible',
				'text' => "Show all outline guide markers for the editor",
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/editor-overview/'

			],
			'change_layer_order' => [
			
				'title' => 'Change Layer Order',
				'target' => '#do_background_layer',
				'alignment' => 'bottom',
				'margin' => '20px 0 0 0',
				'trigger' => ['#module_layers_trigger'],
				'text' => 'Use these arrows to adjust the z-index/stacking order of the currently selected Layer',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/editor-overview/'

			],
			'layout_type' => [
			
				'title' => 'Layout Type',
				'target' => '#rs-layout-type',
				'placer' => '#rs-layout-type label_a',
				'focus' => 'none',
				'alignment' => 'top-left',
				'margin' => '-4px 0 0 -20px',
				'trigger' => ['#module_settings_trigger', '#gst_sl_2'],
				'section' => 'Module General Options -> Layout',
				'scrollTo' => '#form_slider_layout_layout',
				'text' => "Optionally set your Slider to display as a carousel or a Hero Scene",
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/module-layout/'

			],
			'layout_sizing' => [
			
				'title' => 'Layout Sizing',
				'target' => '#rs-layout-sizing',
				'placer' => '#rs-layout-sizing label_a',
				'focus' => 'none',
				'alignment' => 'top-left',
				'margin' => '-4px 0 0 -20px',
				'trigger' => ['#module_settings_trigger', '#gst_sl_2'],
				'section' => 'Module General Options -> Layout',
				'scrollTo' => '#form_slider_layout_layout',
				'text' => 'Choose how the Slider should be displayed on your web page',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/module-layout/'

			],
			'breakpoints' => [
			
				'title' => 'Enable Breakpoints',
				'target' => '#rs-laptop-breakpoint',
				'placer' => '#rs-laptop-breakpoint',
				'focus' => '.tponoffwrap',
				'alignment' => 'top-left',
				'margin' => '-5px 0 0 -20px',
				'trigger' => ['#module_settings_trigger', '#gst_sl_2'],
				'section' => 'Module General Options -> Layout',
				'scrollTo' => '#form_slider_layout_bpoints',
				'text' => "Enable device breakpoints and edit your content's size and position for each viewport",
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/module-layout/'

			],
			'module_content' => [
			
				'title' => 'Module Content Source',
				'target' => '#rs-module-source-wrap',
				'focus' => 'none',
				'alignment' => 'top-left',
				'margin' => '-4px 0 0 -20px',
				'trigger' => ['#module_settings_trigger', '#gst_sl_4'],
				'section' => 'Module General Options -> Content',
				'text' => 'Choose if your Slider should be auto-populated with content from your blog or a social channel',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/module-content/'

			],
			'auto_rotate' => [
			
				'title' => 'Module Content Source',
				'target' => '#rs-autorotate-wrap',
				'placer' => '#rs-autorotate-wrap',
				'focus' => '.tponoffwrap',
				'alignment' => 'top-left',
				'margin' => '-4px 0 0 -20px',
				'trigger' => ['#module_settings_trigger', '#gst_sl_6'],
				'section' => 'Module General Options -> General',
				'scrollTo' => '#form_slidergeneral_general',
				'text' => 'Enable/disable autoplay for the Slider',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/module-general-settings/'

			],
			'lazy_loading' => [
			
				'title' => 'Lazy Loading',
				'target' => '#form_slidergeneral_advanced_loading .collapsable',
				'placer' => '#form_slidergeneral_advanced_loading label_a',
				'focus' => '.ddTP_S',
				'alignment' => 'top-left',
				'margin' => '-4px 0 0 -15px',
				'trigger' => ['#module_settings_trigger', '#gst_sl_10'],
				'section' => 'Module General Options -> Advanced',
				'scrollTo' => '#form_module_advanced',
				'text' => "Enable LazyLoading for your Slider's images for faster page loading",
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/advanced-module-settings/'

			],
			'progress_bar' => [
			
				'title' => 'Progress Bar',
				'target' => '#form_nav_pbara',
				'placer' => '#form_nav_pbara .form_inner_header',
				'focus' => '.tponoffwrap',
				'alignment' => 'left',
				'margin' => '-4px 0 0 -15px',
				'cssClass' => 'form_collector nav_collector',
				'trigger' => ['#module_navigation_trigger', '#gst_nav_1'],
				'section' => 'Navigation Options -> Progress',
				'text' => "Display the Slider's progress with an animated horizontal bar",
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/progress-bar/'

			],
			'navigation_arrows' => [
			
				'title' => 'Navigation Arrows',
				'target' => '#form_nav_arrows',
				'placer' => '#form_nav_arrows .form_inner_header',
				'focus' => '.tponoffwrap',
				'alignment' => 'left',
				'margin' => '-4px 0 0 -15px',
				'cssClass' => 'form_collector nav_collector form_menu_inside',
				'trigger' => ['#module_navigation_trigger', '#gst_nav_2'],
				'section' => 'Navigation Options -> Arrows',
				'text' => "Switch between Slides with navigation Arrows",
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/navigation-arrows/'

			],
			'navigation_bullets' => [
			
				'title' => 'Navigation Bullets',
				'target' => '#form_nav_bullets',
				'placer' => '#form_nav_bullets .form_inner_header',
				'focus' => '.tponoffwrap',
				'alignment' => 'left',
				'margin' => '-4px 0 0 -15px',
				'cssClass' => 'form_collector nav_collector',
				'trigger' => ['#module_navigation_trigger', '#gst_nav_3'],
				'section' => 'Navigation Options -> Bullets',
				'text' => 'Switch between Slides with navigation Bullets',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/navigation-bullets/'

			],
			'navigation_tabs' => [
			
				'title' => 'Navigation Tabs',
				'target' => '#form_nav_tabs',
				'placer' => '#form_nav_tabs .form_inner_header',
				'focus' => '.tponoffwrap',
				'alignment' => 'left',
				'margin' => '-4px 0 0 -15px',
				'cssClass' => 'form_collector nav_collector',
				'trigger' => ['#module_navigation_trigger', '#gst_nav_4'],
				'section' => 'Navigation Options -> Tabs',
				'text' => 'Switch between Slides with navigation Tabs',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/navigation-tabs/'

			],
			'navigation_thumbs' => [
			
				'title' => 'Navigation Thumbs',
				'target' => '#form_nav_thumbs',
				'placer' => '#form_nav_thumbs .form_inner_header',
				'focus' => '.tponoffwrap',
				'alignment' => 'left',
				'margin' => '-4px 0 0 -15px',
				'cssClass' => 'form_collector nav_collector',
				'trigger' => ['#module_navigation_trigger', '#gst_nav_5'],
				'section' => 'Navigation Options -> Thumbs',
				'text' => 'Switch between Slides with navigation Thumbnails',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/navigation-thumbnails/'

			],
			'slide_background' => [
			
				'title' => 'Slide Background',
				'target' => '#form_slidebg',
				'placer' => '#form_slidebg label_a',
				'focus' => '.ddTP_S',
				'alignment' => 'left',
				'margin' => '0 0 0 -20px',
				'cssClass' => 'form_collector slide_settings_collector',
				'trigger' => ['#module_slide_trigger', '#gst_slide_1'],
				'section' => 'Slide Options -> Background',
				'text' => "Set/change the current Slide's main background to an image, video or color",
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/slide-background/'

			],
			'slide_animation' => [
			
				'title' => 'Slide Animation',
				'target' => '#form_slide_transition',
				'placer' => '#active_transitions_innerwrap',
				'focus' => '.rightbutton',
				'alignment' => 'left',
				'margin' => '-3px 0 0 -20px',
				'cssClass' => 'form_collector slide_settings_collector',
				'trigger' => ['#module_slide_trigger', '#gst_slide_2'],
				'section' => 'Slide Options -> Title',
				'text' => "Set the animation for the Slide's main background image when the Slides change",
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/slide-animation/'

			],
			'background_filter' => [
			
				'title' => 'Background Filter',
				'target' => '#form_slidebg_filters',
				'placer' => '#form_slidebg_filters label_a',
				'focus' => '.ddTP_S',
				'alignment' => 'left',
				'margin' => '-4px 0 0 -20px',
				'elementcss' => 'margin-top: -40px',
				'cssClass' => 'form_collector slide_settings_collector',
				'trigger' => ['#module_slide_trigger', '#gst_slide_5'],
				'section' => 'Slide Options -> Title',
				'text' => "Add a CSS image filter to the Slide's main background",
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/slide-filters/'

			],
			'slide_duration' => [
			
				'title' => 'Slide Duration',
				'target' => '#form_slide_progress',
				'placer' => '#form_slide_progress label_a',
				'focus' => '#slide_length',
				'alignment' => 'left',
				'margin' => '-3px 0 0 -20px',
				'elementcss' => 'margin-top: -40px',
				'cssClass' => 'form_collector slide_settings_collector',
				'trigger' => ['#module_slide_trigger', '#gst_slide_8'],
				'section' => 'Slide Options -> Title',
				'text' => 'Adjust the total duration for the current Slide',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/slide-progress/'

			],
			'slide_link' => [
			
				'title' => 'Slide Link',
				'target' => '#form_slidegeneral_linkseo',
				'placer' => '#form_slidegeneral_linkseo label_a',
				'focus' => '.tponoffwrap',
				'alignment' => 'top-left',
				'margin' => '-3px 0 0 -20px',
				'cssClass' => 'form_collector slide_settings_collector',
				'trigger' => ['#module_slide_trigger', '#gst_slide_4'],
				'section' => 'Slide Options -> Title',
				'scrollTo' => '#form_slidegeneral_linkseo',
				'text' => 'Add a link to the entire Slide',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/tags-link/'

			],
			'edit_text' => [
			
				'title' => 'Edit Text',
				'target' => '#form_layercontent_content_text',
				'focus' => '#ta_layertext',
				'alignment' => 'left',
				'margin' => '-3px 0 0 -20px',
				'cssClass' => 'form_collector layer_settings_collector',
				'trigger' => ['._lc_type_text, ._lc_type_button', '#module_layers_trigger', '#gst_layer_1'],
				'section' => 'Layer Options -> Title',
				'text' => 'Edit the content of your text Layers here',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/layer-content/#edit-set-content'

			],
			'font_size' => [
			
				'title' => 'Font Size',
				'target' => '#form_layerstyle_font',
				'focus' => '#layer_font_size_idle',
				'alignment' => 'top-left',
				'margin' => '-3px 0 0 -115px',
				'cssClass' => 'form_collector layer_settings_collector',
				'trigger' => ['._lc_type_text, ._lc_type_button', '#module_layers_trigger', '#gst_layer_3'],
				'section' => 'Layer Options -> Title',
				'scrollTo' => '#form_layer_style',
				'text' => 'Set the font-size for your text Layers here',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/font-colors-styling/'

			],
			'font_family' => [
			
				'title' => 'Font Family',
				'target' => '#form_layerstyle_font',
				'focus' => '.ddTP_C--fontfamily .ddTP_S',
				'alignment' => 'top-left',
				'margin' => '-3px 0 0 -115px',
				'cssClass' => 'form_collector layer_settings_collector',
				'trigger' => ['._lc_type_text, ._lc_type_button', '#module_layers_trigger', '#gst_layer_3'],
				'section' => 'Layer Options -> Title',
				'scrollTo' => '#form_layer_style',
				'text' => 'Set the font-family for your text Layers here',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/font-colors-styling/'

			],
			'font_color' => [
			
				'title' => 'Font Color',
				'target' => '#form_layerstyle_font',
				'focus' => '.rev-colorpicker',
				'alignment' => 'top-left',
				'margin' => '-3px 0 0 -115px',
				'cssClass' => 'form_collector layer_settings_collector',
				'trigger' => ['._lc_type_text, ._lc_type_button', '#module_layers_trigger', '#gst_layer_3'],
				'section' => 'Layer Options -> Title',
				'scrollTo' => '#form_layer_style',
				'text' => 'Set the text color for your Layers here',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/font-colors-styling/'

			],
			'layer_position' => [
			
				'title' => 'Layer Position',
				'target' => '#rs-align-buttons',
				'alignment' => 'left',
				'focus' => 'none',
				'margin' => '-3px 0 0 -20px',
				'cssClass' => 'form_collector layer_settings_collector',
				'trigger' => ['._lc_type_text, ._lc_type_button, ._lc_type_video, ._lc_type_shape, ._lc_type_image, ._lc_type_audio, ._lc_type_object', '#module_layers_trigger', '#gst_layer_2'],
				'section' => 'Layer Options -> Title',
				'text' => "Adjust the Layer's position inside the current Slide",
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/size-position/'

			],
			'layer_animations' => [
			
				'title' => 'Layer Animations',
				'target' => '#form_animation_sframes_keyframes',
				'alignment' => 'left',
				'focus' => 'none',
				'placer' => '#form_animation_sframes_keyframes',
				'margin' => '-26px 0 0 0',
				'cssClass' => 'form_collector layer_settings_collector',
				'trigger' => ['._lc_', '#module_layers_trigger', '#gst_layer_4'],
				'section' => 'Layer Options -> Title',
				'text' => 'Set the in/out animations for your Layer content here',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/layer-animations/'

			],
			'layer_hover' => [
			
				'title' => 'Layer Hover',
				'target' => '#form_layer_hover',
				'alignment' => 'left',
				'focus' => '.tponoffwrap',
				'placer' => '#form_layer_hover label_a',
				'margin' => '57px 0 0 0',
				'cssClass' => 'form_collector layer_settings_collector',
				'trigger' => ['._lc_', '#module_layers_trigger', '#gst_layer_9'],
				'section' => 'Layer Options -> Title',
				'text' => 'Apply hover styles to your Layers',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/mouse-hover-settings/'

			],
			'edit_layer_name' => [
			
				'title' => 'Edit Layer Name',
				'target' => '#do_title_layer',
				'alignment' => 'bottom',
				'margin' => '20px 0 0 0',
				'trigger' => ['._lc_'],
				'section' => 'Layer Options -> Title',
				'text' => 'Change the name of your Layers here to help organize your content'

			],
			'responsive_behavior' => [
			
				'title' => 'Responsive Behavior',
				'target' => '#form_layerposition_basic',
				'alignment' => 'left',
				'focus' => '.tponoffwrap',
				'placer' => '#form_layerposition_basic label_a',
				'margin' => '57px 0 0 0',
				'cssClass' => 'form_collector layer_settings_collector',
				'trigger' => ['._lc_', '#module_layers_trigger', '#gst_layer_13'],
				'section' => 'Layer Options -> Title',
				'text' => 'Adjust the responsive behavior of your content here',
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/responsive-settings/'

			],
			'timeline_preview' => [
			
				'title' => 'Timeline Preview',
				'target' => '.tl_playstop_wrap',
				'focus' => '#timline_process',
				'alignment' => 'top',
				'margin' => '15px 0 0 -3px',
				'cssClass' => 'rb-tooltip-timeline',
				'text' => "Preview the current Slide's animations",
				'linkText' => 'Learn More',
				'link' => 'https://www.sliderrevolution.com/documentation/slide-timeline/'

			],
			'save_module' => [
			
				'title' => 'Save Module',
				'target' => '#save_slider',
				'focus' => 'none',
				'alignment' => 'right-top',
				'margin' => '25px 0px 0px 55px',
				'hidePrevSave' => true,
				'text' => 'Click this button to save your changes'

			],
			'preview_module' => [
			
				'title' => 'Preview Module',
				'target' => '#preview_slider',
				'focus' => 'none',
				'alignment' => 'right-top',
				'margin' => '25px 0px 0px 70px',
				'hidePrevSave' => true,
				'text' => 'Click this button to preview the current Slide'

			]
*/
	
}