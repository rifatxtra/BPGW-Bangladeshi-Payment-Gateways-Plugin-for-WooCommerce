document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('bpgw-settings-form');

    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const sandbox = document.getElementById('bpgw_sandbox_mode').checked;

        fetch(BPGW.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'bpgw_save_settings',
                nonce:  BPGW.nonce,
                sandbox_mode: sandbox ? '1' : '0',
            }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.data.message);
            }
        });
    });

});