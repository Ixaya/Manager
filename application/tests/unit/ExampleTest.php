<?php
 
use PHPUnit\Framework\TestCase;
 
class ExampleTest extends TestCase
{
    /**
	 * __get
	 *
	 * Enables the use of CI super-global without having to define an extra variable.
	 *
	 * @access	public
	 * @param	$var
	 * @return	mixed
	 */
	public function __get($var)
	{
		return get_instance()->$var;
	}
 
    public static function setUpBeforeClass(): void
    {

    }
 
    public function test_main(): void
    {
        $example = 1;
        if($example == 1)
            $this->assertTrue(true);
        else
            $this->assertFalse(false);
    }
}