import { useViewportMatch } from '@wordpress/compose';

import { useToolsPanelDropdownMenuProps } from '../../src/utils/hooks';

jest.mock( '@wordpress/compose', () => ( {
	useViewportMatch: jest.fn(),
} ) );

describe( 'editor utility hooks', () => {
	test( 'positions desktop ToolsPanel menus beside the sidebar', () => {
		useViewportMatch.mockReturnValue( false );
		expect( useToolsPanelDropdownMenuProps() ).toEqual( {
			popoverProps: {
				placement: 'left-start',
				offset: 259,
			},
		} );
		expect( useViewportMatch ).toHaveBeenCalledWith( 'medium', '<' );
	} );

	test( 'uses default ToolsPanel placement on mobile', () => {
		useViewportMatch.mockReturnValue( true );
		expect( useToolsPanelDropdownMenuProps() ).toEqual( {} );
	} );
} );
