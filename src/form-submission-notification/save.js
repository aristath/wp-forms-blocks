import { useInnerBlocksProps, useBlockProps } from '@wordpress/block-editor';
import clsx from 'clsx';

export default function save( { attributes } ) {
	const { type } = attributes;

	return (
		<div
			{ ...useInnerBlocksProps.save(
				useBlockProps.save( {
					className: clsx(
						'wp-block-formblox-form-submission-notification',
						{
							[ `formblox-form-notification-type-${ type }` ]:
								type,
						}
					),
				} )
			) }
		/>
	);
}
