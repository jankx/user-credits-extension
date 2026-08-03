import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-account-tab-credits is-editor-preview',
    });

    return (
        <div {...blockProps}>
            <div style={{ padding: '20px', background: '#f8f9fa', borderRadius: '8px', border: '1px dashed #ddd' }}>
                <h3 style={{ margin: '0 0 16px', fontSize: '16px' }}>{__('Credits', 'jankx')}</h3>
                <div style={{
                    padding: '20px',
                    background: 'linear-gradient(135deg, #65A30D15, #65A30D05)',
                    border: '1px solid #65A30D30',
                    borderRadius: '8px',
                    marginBottom: '16px'
                }}>
                    <div style={{ fontSize: '13px', color: '#666', marginBottom: '4px' }}>{__('Current Balance', 'jankx')}</div>
                    <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#65A30D' }}>0 CREDITS</div>
                </div>
                <div style={{ textAlign: 'center', padding: '20px', color: '#999' }}>
                    <p>{__('Your credit history will appear here', 'jankx')}</p>
                </div>
            </div>
        </div>
    );
}
