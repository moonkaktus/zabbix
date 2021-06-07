<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/CWebTest.php';

/**
 * @backup profiles
 */
class testGraphAxis extends CWebTest {

	public function getDaylightSavingData() {
		return [
			[
				[
					'settings' => ['Time zone' => 'Europe/Riga'],
					'start_period' => '2020-10-25 00:00:00',
					'end_period' => '2020-10-25 08:00:00',
					'name' => 'Riga, Winter, big zoom'
				]
			],
			[
				[
					'settings' => ['Time zone' => 'Europe/Riga'],
					'start_period' => '2020-03-29 00:00:00',
					'end_period' => '2020-03-29 08:00:00',
					'name' => 'Riga, Summer, big zoom'
				]
			],
			[
				[
					'settings' => ['Time zone' => 'Europe/Riga'],
					'start_period' => '2020-10-25 03:00:00',
					'end_period' => '2020-10-25 05:00:00',
					'name' => 'Riga, Winter, small zoom'
				]
			],
			[
				[
					'settings' => ['Time zone' => 'Europe/Riga'],
					'start_period' => '2020-03-29 02:00:00',
					'end_period' => '2020-03-29 04:00:00',
					'name' => 'Riga, Summer, small zoom'
				]
			],
			[
				[
					'settings' => ['Time zone' => 'Australia/Lord_Howe'],
					'start_period' => '2020-10-04 00:00:00',
					'end_period' => '2020-10-04 08:00:00',
					'name' => 'Lord_Howe, Winter, big zoom'
				]
			],
			[
				[
					'settings' => ['Time zone' => 'Australia/Lord_Howe'],
					'start_period' => '2020-04-05 00:00:00',
					'end_period' => '2020-04-05 08:00:00',
					'name' => 'Lord_Howe, Summer, big zoom'
				]
			],
			[
				[
					'settings' => ['Time zone' => 'Australia/Lord_Howe'],
					'start_period' => '2020-10-04 01:00:00',
					'end_period' => '2020-10-04 03:00:00',
					'name' => 'Lord_Howe, Winter, small zoom'
				]
			],
			[
				[
					'settings' => ['Time zone' => 'Australia/Lord_Howe'],
					'start_period' => '2020-04-05 01:00:00',
					'end_period' => '2020-04-05 03:00:00',
					'name' => 'Lord_Howe, Summer, small zoom'
				]
			],
			[
				[
					'settings' => ['Time zone' => 'Australia/Eucla'],
					'start_period' => '2020-03-25 02:00:00',
					'end_period' => '2020-03-25 04:00:00',
					'name' => 'Eucla, Summer, small zoom'
				]
			],
			[
				[
					'settings' => ['Time zone' => 'Australia/Eucla'],
					'start_period' => '2020-03-25 00:00:00',
					'end_period' => '2020-03-25 08:00:00',
					'name' => 'Eucla, Summer, big zoom'
				]
			],
			[
				[
					'settings' => ['Time zone' => 'Pacific/Chatham'],
					'start_period' => '2020-04-05 02:00:00',
					'end_period' => '2020-04-05 04:00:00',
					'name' => 'Chatham, Summer, small zoom'
				]
			],
			[
				[
					'settings' => ['Time zone' => 'Pacific/Chatham'],
					'start_period' => '2020-04-05 01:00:00',
					'end_period' => '2020-04-05 08:00:00',
					'name' => 'Chatham, Summer, big zoom'
				]
			],
			[
				[
					'settings' => ['Time zone' => 'Pacific/Chatham'],
					'start_period' => '2020-09-27 02:00:00',
					'end_period' => '2020-09-27 04:00:00',
					'name' => 'Chatham, Winter, small zoom'
				]
			],
			[
				[
					'settings' => ['Time zone' => 'Pacific/Chatham'],
					'start_period' => '2020-09-27 00:00:00',
					'end_period' => '2020-09-27 08:00:00',
					'name' => 'Chatham, Winter, big zoom'
				]
			]
		];
	}

	/**
	 * Test for checking X axis on graphs depending on time zone and daylight saving changes.
	 *
	 * @dataProvider getDaylightSavingData
	 */
	public function testGraphAxis_DaylightSaving($data) {
		// Set timezone.
		$this->page->login()->open('zabbix.php?action=userprofile.edit')->waitUntilReady();
		$form = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();

		if (CTestArrayHelper::get($data, 'settings.Time zone')) {
			$data['settings']['Time zone'] = CDateTimeHelper::getTimeZoneFormat($data['settings']['Time zone']);
		}

		$form->fill($data['settings']);
		$form->submit();

		// Go to Graphs and set time period.
		$this->page->open('zabbix.php?action=host.view')->waitUntilReady();
		$table = $this->query('xpath://form[@name="host_view"]/table[@class="list-table"]')
				->waitUntilReady()->asTable()->one();
		$table->findRow('Name', 'Dynamic widgets H2')->getColumn('Graphs')->click();
		$this->page->waitUntilReady();
		$this->waitUntilGraphIsLoaded();
		$this->query('id:from')->one()->fill($data['start_period']);
		$this->query('id:to')->one()->fill($data['end_period']);
		$this->query('button:Apply')->one()->waitUntilClickable()->click();
		$this->page->waitUntilReady();
		sleep(1);
		$this->assertScreenshot($this->waitUntilGraphIsLoaded(), $data['name']);
	}

	/**
	 * Function for waiting loader ring.
	 */
	private function waitUntilGraphIsLoaded() {
		try {
			$this->query('xpath://div[contains(@class,"is-loading")]/img')->waitUntilPresent();
		}
		catch (\Exception $ex) {
			// Code is not missing here.
		}

		return $this->query('xpath://div[not(contains(@class,"is-loading"))]/img')->waitUntilPresent()->one();
	}
}
