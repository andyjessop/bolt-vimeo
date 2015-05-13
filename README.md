Bolt Vimeo
==========

This extension pulls video information from a users' Vimeo account and uploads them as content into the bolt database.

It requires that you have a contenttype 'films' set up already, with the following configuration:

	films:
	    name: Films
	    singular_name: Film
	    fields:
	        name:
	            type: text
	            class: large
	            group: content
	        vimeo_id:
	            type: text
	            class: large
	            group: content
	        description:
	            type: textarea
	            group: content
	        created_time:
	            type: datetime
	            group: content
	        modified_time:
	            type: datetime
	            group: content
	        embed_html:
	            type: html
	            group: content

As this uses cron to periodically check for new videos, you'll need to set up cron as per the instructions in the Bolt documentation.

You'll also need to create an app via your Vimeo account, in order to get an access token. Just go to https://developer.vimeo.com/apps. Create a new app then go to the authorization section and generate the token. This should then be put into this package's config.yml file.