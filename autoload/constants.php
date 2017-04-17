<?php
declare(strict_types=1);
namespace gimle;

/**
 * A preset for web mode comparison.
 *
 * @var int
 */
const ENV_WEB = 1;

/**
 * A preset for cli mode comparison.
 *
 * @var int
 */
const ENV_CLI = 2;

/**
 * Developer's desktop/workstation.
 *
 * @var int
 */
const ENV_LOCAL = 4;

/**
 * A preset for development comparison.
 *
 * @var int
 */
const ENV_DEV = 8;

/**
 * A preset for integration comparison.
 *
 * @var int
 */
const ENV_INTEGRATION = 16;

/**
 * A preset for test comparison.
 *
 * @var int
 */
const ENV_TEST = 32;

/**
 * Quality Assurance.
 *
 * @var int
 */
const ENV_QA = 64;

/**
 * User acceptance testing.
 *
 * @var int
 */
const ENV_UAT = 128;

/**
 * A preset for stage comparison.
 *
 * @var int
 */
const ENV_STAGE = 256;

/**
 * A preset for demo comparison.
 *
 * @var int
 */
const ENV_DEMO = 512;

/**
 * A preset for preproduction comparison.
 *
 * @var int
 */
const ENV_PREPROD = 1024;

/**
 * A preset for live comparison.
 *
 * @var int
 */
const ENV_LIVE = 2048;

/**
 * The basename of the site dir.
 *
 * @var string
 */
define(__NAMESPACE__ . '\\SITE_ID', substr(trim(SITE_DIR, '/'), strrpos(trim(SITE_DIR, '/'), '/') + 1));

/**
 * The folder name for the gimle module.
 *
 * @var string
 */
define(__NAMESPACE__ . '\\MODULE_GIMLE', substr(__DIR__, strrpos(__DIR__, '/', -10) + 1, -9));
