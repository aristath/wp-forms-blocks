import { useViewportMatch } from '@wordpress/compose';

/**
 * Keep ToolsPanel menus aligned with the editor sidebar on larger screens.
 *
 * @return {Object} Dropdown menu properties.
 */
export function useToolsPanelDropdownMenuProps() {
	const isMobile = useViewportMatch( 'medium', '<' );
	return ! isMobile
		? {
				popoverProps: {
					placement: 'left-start',
					offset: 259,
				},
		  }
		: {};
}
