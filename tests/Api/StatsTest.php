<?php

declare(strict_types=1);

/*
 * Copyright (C) 2013 Mailgun
 *
 * This software may be modified and distributed under the terms
 * of the MIT license. See the LICENSE file for details.
 */

namespace Mailgun\Tests\Api;

use Mailgun\Exception\InvalidArgumentException;
use Mailgun\Hydrator\ModelHydrator;
use Mailgun\Model\Stats\TotalResponse;
use Mailgun\Model\Stats\TotalResponseItem;
use Nyholm\Psr7\Response;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class StatsTest extends TestCase
{
    protected function getApiClass()
    {
        return 'Mailgun\Api\Stats';
    }

    /**
     * @dataProvider totalProvider
     */
    public function testTotal($queryParameters, $responseData)
    {
        $api = $this->getApiMock(null, null, new ModelHydrator());
        $api->expects($this->once())
            ->method('httpGet')
            ->with('/v3/domain/stats/total', $queryParameters)
            ->willReturn(new Response(200, ['Content-Type' => 'application/json'], \json_encode($responseData)));

        $total = $api->total('domain', $queryParameters);

        $this->assertInstanceOf(TotalResponse::class, $total);
        $this->assertCount(count($responseData['stats']), $total->getStats());
        $this->assertContainsOnlyInstancesOf(TotalResponseItem::class, $total->getStats());

        $event = $queryParameters['event'];
        $responseStat = $total->getStats()[0];
        $statGetter = 'get'.ucwords($event);

        if ('failed' !== $event) {
            $expectedTotal = $responseData['stats'][0][$event]['total'];
            $actualTotal = $responseStat->$statGetter()['total'];
        }

        if ('failed' === $event) {
            $expectedTotal = $responseData['stats'][0][$event]['permanent']['total'];
            $actualTotal = $responseStat->$statGetter()['permanent']['total'];
        }

        $this->assertEquals($expectedTotal, $actualTotal);
    }

    public function testTotalInvalidArgument()
    {
        $this->expectException(InvalidArgumentException::class);

        $api = $this->getApiMock();
        $api->total('');
    }

    public function totalProvider()
    {
        return [
            'accepted events' => [
                'queryParameters' => [
                    'event' => 'accepted',
                ],
                'responseData' => $this->generateTotalResponsePayload([
                    [
                        'time' => $this->formatDate('-7 days'),
                        'accepted' => [
                            'outgoing' => 10,
                            'incoming' => 5,
                            'total' => 15,
                        ],
                    ],
                ]),
            ],
            'failed events' => [
                'queryParameters' => [
                    'event' => 'failed',
                ],
                'responseData' => $this->generateTotalResponsePayload([
                    [
                        'time' => $this->formatDate('-7 days'),
                        'failed' => [
                            'permanent' => [
                                'bounce' => 4,
                                'delayed-bounce' => 1,
                                'suppress-bounce' => 1,
                                'suppress-unsubscribe' => 2,
                                'suppress-complaint' => 3,
                                'total' => 10,
                            ],
                            'temporary' => [
                                'espblock' => 1,
                            ],
                        ],
                    ],
                ]),
            ],
            'delivered events' => [
                'queryParameters' => [
                    'event' => 'delivered',
                ],
                'responseData' => $this->generateTotalResponsePayload([
                    [
                        'time' => $this->formatDate('-7 days'),
                        'delivered' => [
                            'smtp' => 15,
                            'http' => 5,
                            'total' => 20,
                        ],
                    ],
                ]),
            ],
            'clicked events' => [
                'queryParameters' => [
                    'event' => 'clicked',
                ],
                'responseData' => $this->generateTotalResponsePayload([
                    [
                        'time' => $this->formatDate('-7 days'),
                        'clicked' => [
                            'total' => 7,
                        ],
                    ],
                ]),
            ],
            'opened events' => [
                'queryParameters' => [
                    'event' => 'opened',
                ],
                'responseData' => $this->generateTotalResponsePayload([
                    [
                        'time' => $this->formatDate('-7 days'),
                        'opened' => [
                            'total' => 19,
                        ],
                    ],
                ]),
            ],
            'unsubscribed events' => [
                'queryParameters' => [
                    'event' => 'unsubscribed',
                ],
                'responseData' => $this->generateTotalResponsePayload([
                    [
                        'time' => $this->formatDate('-7 days'),
                        'unsubscribed' => [
                            'total' => 10,
                        ],
                    ],
                ]),
            ],
            'stored events' => [
                'queryParameters' => [
                    'event' => 'stored',
                ],
                'responseData' => $this->generateTotalResponsePayload([
                    [
                        'time' => $this->formatDate('-7 days'),
                        'stored' => [
                            'total' => 12,
                        ],
                    ],
                ]),
            ],
        ];
    }

    private function generateTotalResponsePayload(array $stats, $start = '-7 days', $end = 'now', $resolution = 'day')
    {
        return [
            'end' => $this->formatDate($end),
            'resolution' => $resolution,
            'start' => $this->formatDate($start),
            'stats' => $stats,
        ];
    }

    private function formatDate($time = 'now')
    {
        return (new \DateTime($time, new \DateTimeZone('UTC')))->format('D, d M Y H:i:s T');
    }
}
