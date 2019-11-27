# radiam-hubzero
Radiam component for HubZero

## Installation

Copy the com_radiam directory to your HubZero installation, in the components folder.  It will be located somewhere like `/var/www/hubname/app/components` so when you're done you have a new directory `/var/www/hubname/app/components/com_radiam`.

From the command line of your HubZero instance, initialize the Radiam component database:

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

Log into your HubZero administration section.  Click on the menu for Components and find Radiam, click on it.


## Removal

Remove the database tables:

```
cd /var/www/hubname

php muse migration -d=down -e=com_radiam
```

Then delete the entire contents of the component:

```
cd /var/www/hubname/app/components

rm -rf com_radiam
```