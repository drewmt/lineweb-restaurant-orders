<?php
/**
 * Opening-hours schedule tests.
 *
 * @package SnapOrder
 */

use PHPUnit\Framework\TestCase;

class SnapOrder_Settings_Schedule_Unit_Test extends TestCase {

	public function test_normal_day_interval() {
		$schedule = array(
			'monday' => array(
				'is_open' => '1',
				'open'    => '09:00',
				'close'   => '22:00',
			),
		);

		$this->assertTrue( SnapOrder_Settings::schedule_is_open( $schedule, 'monday', 'sunday', '12:30' ) );
		$this->assertFalse( SnapOrder_Settings::schedule_is_open( $schedule, 'monday', 'sunday', '23:00' ) );
	}

	public function test_overnight_interval_carries_into_next_day() {
		$schedule = array(
			'friday'   => array(
				'is_open' => '1',
				'open'    => '18:00',
				'close'   => '02:00',
			),
			'saturday' => array(
				'is_open' => '0',
				'open'    => '09:00',
				'close'   => '22:00',
			),
		);

		$this->assertTrue( SnapOrder_Settings::schedule_is_open( $schedule, 'friday', 'thursday', '23:30' ) );
		$this->assertTrue( SnapOrder_Settings::schedule_is_open( $schedule, 'saturday', 'friday', '01:30' ) );
		$this->assertFalse( SnapOrder_Settings::schedule_is_open( $schedule, 'saturday', 'friday', '03:00' ) );
	}

	public function test_equal_times_mean_open_all_day() {
		$schedule = array(
			'monday' => array(
				'is_open' => '1',
				'open'    => '00:00',
				'close'   => '00:00',
			),
		);

		$this->assertTrue( SnapOrder_Settings::schedule_is_open( $schedule, 'monday', 'sunday', '14:00' ) );
	}
}
