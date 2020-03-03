<?php
/**
 * Copyright (c) Enalean, 2020-Present. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace Tuleap\User\Account;

use CSRFSynchronizerToken;
use Mockery as M;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tuleap\Request\ForbiddenException;
use Tuleap\TemporaryTestDirectory;
use Tuleap\Test\Builders\HTTPRequestBuilder;
use Tuleap\Test\Builders\LayoutBuilder;
use Tuleap\Test\Builders\TemplateRendererFactoryBuilder;
use Tuleap\Test\Builders\UserTestBuilder;

final class DisplayAccountInformationControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration, TemporaryTestDirectory;

    /**
     * @var DisplayAccountInformationController
     */
    private $controller;
    /**
     * @var \PFUser
     */
    private $user;
    private $event_manager;

    protected function setUp(): void
    {
        $this->event_manager = new class implements EventDispatcherInterface {
            private $disableRealNameChange;

            public function dispatch(object $event)
            {
                if ($event instanceof AccountInformationPreUpdateEvent) {
                    if ($this->disableRealNameChange) {
                        $event->disableChangeRealName();
                    }
                }
                return $event;
            }

            public function disableRealNameChange()
            {
                $this->disableRealNameChange = true;
            }
        };

        $this->controller = new DisplayAccountInformationController(
            $this->event_manager,
            TemplateRendererFactoryBuilder::get()->withPath($this->getTmpDir())->build(),
            M::mock(CSRFSynchronizerToken::class)
        );

        $this->user = UserTestBuilder::aUser()
            ->withId(110)
            ->withUserName('alice')
            ->withRealName('Alice FooBar')
            ->withAddDate((new \DateTimeImmutable())->getTimestamp())
            ->withLanguage(M::spy(\BaseLanguage::class))
            ->build();
    }

    public function testItThrowExceptionForAnonymous(): void
    {
        $this->expectException(ForbiddenException::class);

        $this->controller->process(
            HTTPRequestBuilder::get()->withAnonymousUser()->build(),
            LayoutBuilder::build(),
            []
        );
    }

    public function testItRendersThePage(): void
    {
        ob_start();
        $this->controller->process(
            HTTPRequestBuilder::get()->withUser($this->user)->build(),
            LayoutBuilder::build(),
            []
        );
        $output = ob_get_clean();
        $this->assertStringContainsString('Account information', $output);
    }

    public function testItRendersThePageWithRealnameEditable(): void
    {
        ob_start();
        $this->controller->process(
            HTTPRequestBuilder::get()->withUser($this->user)->build(),
            LayoutBuilder::build(),
            []
        );
        $output = ob_get_clean();
        $this->assertStringContainsString('name="realname" value="Alice FooBar"', $output);
    }

    public function testItRendersThePageWithRealnameReadOnly(): void
    {
        $this->event_manager->disableRealNameChange();

        ob_start();
        $this->controller->process(
            HTTPRequestBuilder::get()->withUser($this->user)->build(),
            LayoutBuilder::build(),
            []
        );
        $output = ob_get_clean();
        $this->assertStringContainsString('<p>Alice FooBar</p>', $output);
        $this->assertStringNotContainsString('name="realname" value="Alice FooBar"', $output);
    }
}