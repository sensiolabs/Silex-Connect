SensioLabsConnect Silex Service Provider
========================================

Installation
------------

Use [composer](http://getcomposer.org) to install the provider

    php composer.phar require sensiolabs/silex-connect

Usage
-----

> **Note:** You should registrer `BuzzServiceProvider` if needed

Register the service provider on your app:

    $app->register(new ConnectServiceProvider(), array(
        'sensiolabs_connect.app_id'     => 'YOUR_APP_ID',
        'sensiolabs_connect.app_secret' => 'YOUR_APP_SECRET',
        'sensiolabs_connect.app_scope'  => 'YOUR_APP_SCOPE',
    ));

Then, use the `sensiolabs_connect` authentication mechanism anywhere in your
security configuration:

    $app->register(new SecurityServiceProvider(), array(
        'security.firewalls' => array(
            'default' => array(
                'pattern' => '^',
                //'anonymous' => true,
                'sensiolabs_connect' => true,
                'logout' => true,
                'users' => $app->share(function () use ($app) {
                    return new ConnectInMemoryUserProvider(array(
                        '4aed4f5d-e0cb-4320-902f-885fddaa7d15' => array('ROLE_ADMIN', 'ROLE_CONNECT_USER'),
                    ));
                }),
            ),
        ),
    ));

If you don't want to persist your users, you can use `ConnectInMemoryUserProvider`:

    'users' => $app->share(function () use ($app) {
        return new ConnectInMemoryUserProvider(array(
            '4aed4f5d-e0cb-4320-902f-885fddaa7d15' => array('ROLE_ADMIN'),
        ));
    }),

If the user is not defined, it will be created for you with the special
`ROLE_CONNECT_USER` role. If you want some special roles for some users, just
pass them to the constructor (like for
`4aed4f5d-e0cb-4320-902f-885fddaa7d15`).

The API user is available through the security token:

    $user = $app['security']->getToken()->getApiUser();

You can generate a link to the SensioLabs Connect login page (replace
`default` with the name of your firewall entry):

    <a href="{{ path('sensiolabs_connect.oauth_login.default') }}">Connect</a>

You can also specify the target URL after connection:

    <a href="{{ path('sensiolabs_connect.oauth_login.default') }}?target=XXX">Connect</a>

You can also get access to the API root object:

    $accessToken = $app['security']->getToken()->getAccessToken();

    $root = $app['sensiolabs_connect.api']->getRoot();
    $user = $root->getCurrentUser();

If you want to get the root API for the current user, you can just do the
following:

    $root = $app['sensiolabs_connect.api_root']();
