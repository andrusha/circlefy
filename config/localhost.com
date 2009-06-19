<VirtualHost *:80>

        RewriteEngine on

        RewriteRule /network$ /index.php?page=network
        RewriteRule /relevancy_settings$ /index.php?page=rel_settings
        RewriteRule /messeges$ /index.php?page=messeges
        RewriteRule /profile$ /index.php?page=profile
        RewriteRule /accounting$ /index.php?page=accounting
        RewriteRule /logs$ /index.php?page=logs
        RewriteRule /groups$ /index.php?page=groups_manage
        RewriteRule /people_search$ /index.php?page=search_people
        RewriteRule /account_settings$ /index.php?page=account_settings
        RewriteRule /messeges_view$ /index.php?page=messeges_view
        RewriteRule /create_group$ /index.php?page=create_group
        RewriteRule /profile_edit$ /index.php?page=profile_edit
        RewriteRule /view_logs$ /index.php?page=view_logs
        RewriteRule /search_groups$ /index.php?page=search_groups

        RewriteRule /group/([0-9])$ /index.php?page=group&group=$1
        RewriteRule /edit_group/([0-9])$ /index.php?page=group_edit&group=$1
        RewriteRule /([0-9])$ /index.php?fid=$1
        RewriteRule /homepage_loged_out$ /index.php?page=homepage_loged_out


ServerName localhost.com
ServerAlias localhost.com
DocumentRoot /htdocs/tap_repo_taso/
ErrorLog /var/log/apache2/error.log
#CustomLog /home/infotutors/logs/access.log common
</VirtualHost>
<Directory /htdocs/tap_repo_taso/>
        Order allow,deny
        Allow from all
        AllowOverride AuthConfig
</Directory>

