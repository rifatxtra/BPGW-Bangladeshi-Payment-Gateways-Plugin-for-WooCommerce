(function () {
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { getSetting } = window.wc.wcSettings;
    const { createElement } = window.wp.element;

    const settings = getSetting('bpgw_sslcommerz_data', {});

    const SSLCommerzLabel = () => createElement(
        'span',
        { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
        settings.logo && createElement('img', {
            src: settings.logo,
            alt: 'SSLCommerz',
            style: { height: '24px' }
        }),
        createElement('span', null, settings.title || 'SSLCommerz')
    );

    const SSLCommerzContent = () => createElement(
        'p',
        { style: { margin: '8px 0', fontSize: '14px', color: '#666' } },
        settings.description || ''
    );

    registerPaymentMethod({
        name: 'bpgw_sslcommerz',
        label: createElement(SSLCommerzLabel),
        content: createElement(SSLCommerzContent),
        edit: createElement(SSLCommerzContent),
        canMakePayment: () => true,
        ariaLabel: 'SSLCommerz payment method',
        supports: {
            features: ['products'],
        },
    });
})();