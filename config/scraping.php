<?php

declare(strict_types=1);

return [
    'tls_verify' => filter_var(env('SCRAPE_TLS_VERIFY', true), FILTER_VALIDATE_BOOLEAN),

    'proxy_max_retries' => (int) env('SCRAPE_PROXY_MAX_RETRIES', 5),

    'connect_timeout' => (int) env('SCRAPE_CONNECT_TIMEOUT', 5),

    'timeout' => (int) env('SCRAPE_TIMEOUT', 20),

    'proxy_empty_release_seconds' => (int) env('SCRAPE_PROXY_EMPTY_RELEASE_SECONDS', 60),

    'urls' => [
        'https://www.amazon.eg/-/en/Dell-Alienware-Horizon-Gaming-Backpack-AW423P/dp/B0CPVPWHYR',
        'https://www.jumia.com.eg/m-design-square-bottle-with-strap-black-500ml-75072-134994528.html',
        'https://www.noon.com/egypt-en/arion-43-inch-full-hd-smart-android-tv-a-grade-panel-frameless-design-wi-fi-hdmi-usb-connectivity-ar-43-t4s/ZE047C976201DE9F90F32Z/p/?o=d4f41684dc6dfa0d',
    ],
];
