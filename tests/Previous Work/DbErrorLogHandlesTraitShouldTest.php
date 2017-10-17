<?php
/**
 * Created by PhpStorm.
 * User: nenad
 * Date: 5/29/17
 * Time: 3:20 PM
 */

use App\Http\Traits\DbErrorLogHandlesTrait;

/**
 * @covers \App\Http\Traits\DbErrorLogHandlesTrait
 */
class DbErrorLogHandlesTraitShouldTest extends TestCase
{
    use DbErrorLogHandlesTrait;

    /**
     * @test
     * @covers \App\Http\Traits\DbErrorLogHandlesTrait::createTimestampFile()
     */
    public function creating_timestamp_file_on_pdo_exception()
    {
        // Arrange
        $filename = config('mail.db_error_log_timestamp_filename');

        // Act
        $this->createTimestampFile();

        // Assert
        $this->assertFileExists($filename);
    }

    /**
     * @test
     * @covers \App\Http\Traits\DbErrorLogHandlesTrait::getTimestampFromFile()
     */
    public function returned_timestamp_from_existing_file()
    {
        // Arrange
        $this->createTimestampFile();

        // Act
        $result = (integer) $this->getTimestampFromFile();

        // Assert
        $this->assertTrue(is_int($result));
    }

    /**
     * @test
     * @covers \App\Http\Traits\DbErrorLogHandlesTrait::timestampFileExists()
     */
    public function timestamp_file_exists()
    {
        // Arrange
        $filename = config('mail.db_error_log_timestamp_filename');
        $this->createTimestampFile();

        // Act
        $result = $this->timestampFileExists();

        // Assert
        $this->assertTrue($result);
        $this->assertFileExists($filename);
    }

    /**
     * @test
     * @covers \App\Http\Traits\DbErrorLogHandlesTrait::timestampFileExists()
     */
    public function timestamp_file_not_exists()
    {
        // Arrange
        \Illuminate\Support\Facades\Storage::disk('root')->delete(config('mail.db_error_log_timestamp_filename'));

        // Act
        $result = $this->timestampFileExists();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * @test
     * @covers \App\Http\Traits\DbErrorLogHandlesTrait::getTimestampDifference()
     */
    public function timestamp_difference_result()
    {
        // from config file,

        // Arrange
        $this->createTimestampFile();
        $timestamp = \Carbon\Carbon::now()->subMinute(10)->timestamp;

        // Act
        $result = $this->getTimestampDifference($timestamp);

        // Assert
        $this->assertSame($result, (integer) config('mail.db_error_log_frequency'));
    }
}
