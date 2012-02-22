<?php
// vim: set expandtab tabstop=4 shiftwidth=4 syntax=php:

$spec = Pearfarm_PackageSpec::create(array(Pearfarm_PackageSpec::OPT_BASEDIR => dirname(__FILE__)))
             ->setName('jqjobs')
             ->setChannel('apinstein.pearfarm.org')
             ->setSummary('A simple job queue engine for PHP.')
             ->setDescription('Easily allow your applications to enqueue jobs and run workers to process jobs. Supports multiple queue stores, priorities, locking, etc.')
             ->setReleaseVersion('1.0.12')
             ->setReleaseStability('stable')
             ->setApiVersion('1.0.3')
             ->setApiStability('stable')
             ->setLicense(Pearfarm_PackageSpec::LICENSE_MIT)
             ->setNotes('Initial public release. Has been used in production for over 2.5 million jobs.')
             ->addMaintainer('lead', 'Alan Pinstein', 'apinstein', 'apinstein@mac.com')
             ->addPackageDependency('mp', 'apinstein.pearfarm.org')
             ->addGitFiles()
             ->addFilesRegex('/migrations/', 'data')
             ->addExcludeFiles(array('.gitignore', 'pearfarm.spec'))
             ->addExcludeFilesRegex('/^externals/')
             ;
