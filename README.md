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

Failures Handling
-----------------

> **Note**: this feature requires `sensiolabs/connect` `v3.0.0`

Several errors can occurred during the OAuth dance, for example the user can
deny your application or the scope you defined can be different from what you
 selected while creating your application on SensioLabsConnect.
Theses failures need to be handled.

Since `sensiolabs/connect` `v3.0.0`, failures handling is restored to the default
Symfony failure handling.

Therefore, if an error occurred, the error is stored in the session (with a
fallback on query attributes) and the user is redirected to the route/path
specificed in `failure_path` entry of the `sensiolabs_connect` entry of your
firewall.

> **Warning**: You **need** to specifiy `failure_path`. If you don't, the user
> will be redirected back to `/login`, meaning that may launch the
> SensioLabsConnect authentication and redirect the user to SensioLabsConnect
> which can lead to a redirection loop.

This means you need to fetch the authencation error if there is one and display
it. This is similar to what you do for a typical login form using the
`security.last_error` services.
You can refer to the `SecurityServiceProvider` documentation for more informations.
