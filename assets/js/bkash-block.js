(function () {
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { getSetting } = window.wc.wcSettings;
    const { createElement } = window.wp.element;

    const settings = getSetting('bpgw_bkash_data', {});

    const BkashLabel = () => createElement(
        'span',
        { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
        settings.logo && createElement('img', {
            src: settings.logo,
            alt: 'bKash',
            style: { height: '24px' }
        }),
        createElement('span', null, settings.title || 'bKash')
    );

    const BkashContent = () => createElement(
        'p',
        { style: { margin: '8px 0', fontSize: '14px', color: '#666' } },
        settings.description || ''
    );

    registerPaymentMethod({
        name: 'bpgw_bkash',
        label: createElement(BkashLabel),
        content: createElement(BkashContent),
        edit: createElement(BkashContent),
        canMakePayment: () => true,
        ariaLabel: 'bKash payment method',
        supports: {
            features: ['products'],
        },
    });
})();