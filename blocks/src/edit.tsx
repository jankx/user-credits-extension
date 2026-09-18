import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-account-tab-credits',
    });

    return (
        <div {...blockProps}>
            <InnerBlocks />
        </div>
    );
}
