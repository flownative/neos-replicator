Neos Replicator |version|
=========================

This documentation covering version |release| has been rendered at: |today|

The Neos Replicator allows to replicate content from one site to another, to provide staging and synchronisation.

.. note:: This documentation serves as a specification for the package to be built. None of this is
          currently implemented!

Overview
--------

Use-cases
~~~~~~~~~

The simplest use-case for this is a simple replication from one server to another. This can be used to
have an editing server that is not reachable from the internet. Content is then pushed to a live
server (that has no editors, a read-only database connection, …):

.. image:: Images/editing-live.png

As simple but working in the other direction: developers can pull live content to their machine, so they
can work with content beyond *lorem ipsum*:

.. image:: Images/dev-live.png

Only a bit more advanced: add a staging machine to the first example and use it to have your legal department
or management double check things before pushing to the live server:

.. image:: Images/editing-staging-live.png

One way to do A/B testing could be to replicate different content to two live servers. After drawing conclusions
from the test, replicate the "winning" version to both live servers. This could be done by using three workspaces,
two for the variants and a final workspace. The variant workspaces are replicated to the live workspace on one of
the servers each. After the winning variant has been published to live on the editing server, that is is pushed to
both servers, again into the live workspace, overriding the previous content.

.. image:: Images/editing-livea-liveb.png

Installation
------------

The package needs to be available in all Neos instances that will be involved with the replication.

It can be installed by

.. code-block:: none

  $ composer require flownative/neos-replicator

Afterwards it needs to be configured.

Configuration
-------------

The configuration is split in two areas of responsibility. There is the setup of available targets to
be used in replication and there is the configuration of the replication itself.

Endpoint configuration
~~~~~~~~~~~~~~~~~~~~~~

This is done in *Settings.yaml* as follows:

.. literalinclude:: ../Configuration/Settings.yaml
   :language: yaml
   :lines: 1-18
   :emphasize-lines: 7-

You can have as many endpoints as you need, and you can define them all at every system or put only the
needed ones on every setup, depending on the replication you actually need to run.

Replication configuration
~~~~~~~~~~~~~~~~~~~~~~~~~

Replications define what is replicated between systems:

- which data (content, users, assets, …)
- the source and target workspace
- endpoints to replicate from/to
- filters to exclude or include items

This is done in *Settings.yaml* as follows:

.. literalinclude:: ../Configuration/Settings.yaml
   :language: yaml
   :lines: 24-43
