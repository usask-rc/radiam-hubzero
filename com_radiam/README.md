
Installation
============

When downloaded as a ZIP file, unzip and place the resulting directory into YOUR_HUB_WEBROOT/app/components

The final result should look like:

    /app
    .. /components
    .. .. /com_radiam
    .. .. .. /admin
    .. .. .. /config
    .. .. .. /helpers
    .. .. .. /migrations
    .. .. .. /models
    .. .. .. /site
    .. .. .. radiam.xml

The component comes with migrations that can be run via the command-line utility, `muse`, that
comes with all hubs.

The first migration is required for the component to function properly and installs just the
database tables and registers the component with the CMS. The other migration is optional and
installs sample content.

From the command-line, starting in your hub's root directory, you can run all migrations with:

    $ php muse migration -f

Documentation on `muse` can be found at https://help.hubzero.org/documentation/22/webdevs/muse