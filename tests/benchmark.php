<?php

/**
 * A simple benchmark script for measuring container performance.
 *
 * @package StellarWP\Container
 */

namespace StellarWP\Container\Benchmark;

use League\CLImate\CLImate;
use StellarWP\Container\Container;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

class ConcreteContainer extends Container {
    public function config() {
        return [
            \DateTime::class => null,
            'stdClass'       => function () {
                return new \stdClass();
            },
            'cURL'           => function () {
                $ch = curl_init();
                curl_setopt( $ch, CURLOPT_URL, 'https://example.com' );
                curl_setopt( $ch, CURLOPT_POST, true );

                return $ch;
            },
            'bread'          => function ( $container ) {
                return new \stdClass(
                    $container->get( 'flour' ),
                    $container->get( 'water' ),
                    $container->get( 'yeast' ),
                    $container->get( 'salt' )
                );
            },
            'flour'          => function () {
                return new \stdClass();
            },
            'jelly'          => function () {
                return new \stdClass();
            },
            'peanutbutter'   => function () {
                return new \stdClass();
            },
            'salt'           => function () {
                return new \stdClass();
            },
            'water'          => function () {
                return new \stdClass();
            },
            'yeast'          => function () {
                return new \stdClass();
            },
            'PBandJ'         => function ( $container ) {
                return new \stdClass(
                    $container->get( 'bread' ),
                    $container->get( 'peanutbutter' ),
                    $container->get( 'jelly' )
                );
            },
        ];
    }
}

/**
 * Execute a closure the given number of times and return the time (in nanoseconds) it took to run.
 */
function profile( $callable, $iterations ) {
    $start_time = hrtime( true );
    for ( $i = 0; $i < $iterations; $i++ ) {
        $callable();
    }
    $end_time = hrtime( true );

    return number_format( $end_time - $start_time );
}

$console    = new CLImate();
$container  = new ConcreteContainer();
$iterations = 1000000;

$console->out( "PHP version: \t" . PHP_VERSION )
    ->out( "Iterations:\t" . number_format( $iterations ) );

$console->break()
    ->cyan( 'Resolving an abstract with a NULL definition' )
    ->table( [
        [
            'Benchmark'   => 'Direct',
            'Timing (ns)' => profile( function () {
                return new \DateTime();
            }, $iterations )
        ],
        [
            'Benchmark'   => 'Cached',
            'Timing (ns)' => profile( function () use ( $container ) {
                return $container->get( \DateTime::class );
            }, $iterations )
        ],
        [
            'Benchmark'   => 'Uncached',
            'Timing (ns)' => profile( function () use ( $container ) {
                return $container->make( \DateTime::class );
            }, $iterations )
        ],
     ] );

$console->break()
    ->cyan( 'Resolving simple, built-in PHP object' )
    ->table( [
        [
            'Benchmark'   => 'Direct',
            'Timing (ns)' => profile( function () {
                return new \stdClass();
            }, $iterations )
        ],
        [
            'Benchmark'   => 'Cached',
            'Timing (ns)' => profile( function () use ( $container ) {
                return $container->get( 'stdClass' );
            }, $iterations )
        ],
        [
            'Benchmark'   => 'Uncached',
            'Timing (ns)' => profile( function () use ( $container ) {
                return $container->make( 'stdClass' );
            }, $iterations )
        ],
    ] );

$console->break()
    ->cyan( 'Building a more complex, built-in PHP object' )
    ->table( [
        [
            'Benchmark'   => 'Direct',
            'Timing (ns)' => profile( function () {
                $ch = curl_init();
                curl_setopt( $ch, CURLOPT_URL, 'https://example.com' );
                curl_setopt( $ch, CURLOPT_POST, true );

                return $ch;
            }, $iterations )
        ],
        [
            'Benchmark'   => 'Cached',
            'Timing (ns)' => profile( function () use ( $container ) {
                return $container->get( 'cURL' );
            }, $iterations )
        ],
        [
            'Benchmark'   => 'Uncached',
            'Timing (ns)' => profile( function () use ( $container ) {
                return $container->make( 'cURL' );
            }, $iterations )
        ],
    ] );

$console->break()
    ->cyan( 'Building an object with recursive dependencies' )
    ->table( [
        [
            'Benchmark'   => 'Direct',
            'Timing (ns)' => profile( function () {
                $flour = new \stdClass();
                $water = new \stdClass();
                $yeast = new \stdClass();
                $salt  = new \stdClass();
                $bread = new \stdClass( $flour, $water, $yeast, $salt );
                $pb    = new \stdClass();
                $jelly = new \stdClass();

                return new \stdClass( $bread, $pb, $jelly );
            }, $iterations )
        ],
        [
            'Benchmark'   => 'Cached',
            'Timing (ns)' => profile( function () use ( $container ) {
                return $container->get( 'PBandJ' );
            }, $iterations )
        ],
        [
            'Benchmark'   => 'Uncached',
            'Timing (ns)' => profile( function () use ( $container ) {
                return $container->make( 'PBandJ' );
            }, $iterations )
        ],
    ] );
