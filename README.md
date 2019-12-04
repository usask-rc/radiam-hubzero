# radiam-hubzero

Radiam component and module for HubZero

## Installation

Copy the com_radiam directory to your HubZero installation, in the components folder.  It will be located somewhere like `/var/www/hubname/app/components` so when you're done you have a new directory `/var/www/hubname/app/components/com_radiam`.

Copy the mod_radiam directory to your HubZero installation, in the modules folder.  It will be located somewhere like `/var/www/hubname/app/modules` so when you're done you have a new directory `/var/www/hubname/app/modules/mod_radiam`.

From the command line of your HubZero instance, initialize the Radiam database objects:

```
cd /var/www/hubname

# Dry run, see what will be done:
php muse migration
```

That command should list at least two database migrations that will be run.  If it looks OK, you can proceed:

```
# Full run this time
php muse migration -f
```

## Configuration

Log into your HubZero administration section.  Click on the menu for Components and find Radiam, click on it.  Edit the `radiam_host_url` setting to match where your Radiam instance is.

Click on the menu for modules and add a new module.  Find Radiam.  Configure it with a location of "memberDashboard".

## Viewing

As a regular HubZero user, navigate to your dashboard.  Install the Radiam module in your dashboard.


## Removal

Remove the database tables and entension entries:

```
cd /var/www/hubname

php muse migration -d=down -e=com_radiam -f
```

Then delete the entire contents of the component:

```
cd /var/www/hubname/app/components

rm -rf com_radiam
```