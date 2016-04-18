# Drupal Deploy

- content source and sink
- sources have a sink connection configured
    - name
    - description
    - endpoint url
    - authentication settings
- stuff is either added to a deployment plan (for later porcessing) or deployed directly
- using a manually configured hook was (is?) a way to deploy everything on save

## Use cases

from http://buytaert.net/improving-drupal-content-workflow

Before jumping to the technical details, let's talk a bit more about the problems these modules are solving.

1. Cross-site content staging — In this case you want to synchronize content from one site to another. The first site may be a staging site where content editors make changes. The second site may be the live production site. Changes are previewed on the stage site and then pushed to the production site. More complex workflows could involve multiple staging environments like multiple sites publishing into a single master site.
2. Content branching — For a new product launch you might want to prepare a version of your site with a new section on the site featuring the new product. The new section would introduce several new pages, updates to existing pages, and new menu items. You want to be able to build out the updated version in a self-contained 'branch' and merge all the changes as a whole when the product is ready to launch. In an election case scenario, you might want to prepare multiple sections; one for each candidate that could win.
3. Preview your site — When you're building out a new section on your site for launch, you want to preview your entire site, as it will look on the day it goes live. This is effectively content staging on a single site.
4. Offline browse and publish — Here is a use-case that Pfizer is trying to solve. A sales rep goes to a hospital and needs access to information when there is no wi-fi or a slow connection. The site should be fully functional in offline mode and any changes or forms submitted, should automatically sync and resolve conflicts when the connection is restored.
5. Content recovery — Even with confirmation dialogs, people delete things they didn’t want to delete. This case is about giving users the ability to “undelete” or recover content that has been deleted from their database.
6. Audit logs — For compliance reasons, some organizations need all content revisions to be logged, with the ability to review content that has been deleted and connect each action to a specific user so that employees are accountable for their actions in the CMS.

### In Neos…

1. is solved by core functionality (workspaces)
2. is solved by core functionality (workspaces)
3. is solved by core functionality (workspaces)
4. -
5. until a change/deletion is published to live, it can be undone using core functionality (workspaces)
6. the event log allows some auditing, but does not keep full details

# Data to care about

- nodes
- workspaces
- resources & related data
- users

- nodes, node references, objects in properties
- "links" in "body text"!?

- Elasticsearch indexing? Other "hooks"?

# Implementation

## Communication

- REST API?
- replication.io? pouchdb for offline working in the browser?
- deletions not "trackable" once published to live!

## conflict handling

- update only / overwrite
- keep/delete items only on target

## authentication

- API keys, fixed in settings
- later oauth or a "regular" user on the target?

## services needed

- fetch all node identifiers, filter by: workspace, modified since
- assets: basically crud (including tags, collections)
- nodes: basically crud
