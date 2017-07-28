<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\Action;
use PHPUnit\Framework\TestCase;
use Prophecy\{
	Argument,
	Prophet
};

/**
 * Implementation of ActionTest
 */
class ActionTest extends TestCase
{
	public function testName()
	{
		$action = new Action();
		$action->setText("[Foo::action1]");
		$action->parse();
		$this->assertEquals("Foo", $action->getClassName());
		$this->assertEquals("action1", $action->getName());
		$this->assertEquals("Foo::action1", $action->getKey());
	}

	public function testSingle()
	{
		$action = new Action();
		$action->setText("");
		$action->parse();
		$this->assertTrue($action->getStart());
		$this->assertFalse($action->getNext());
	}

	public function testStart()
	{
		$action = new Action();
		$action->setText("start end");
		$action->parse();
		$this->assertTrue($action->getStart());
	}

	public function testReadonly()
	{
		$action = new Action();
		$action->setText("readonly");
		$action->parse();
		$this->assertTrue($action->getReadonly());
	}

	public function testJoin()
	{
		$action = new Action();
		$action->setText(">|");
		$action->parse();
		$this->assertTrue($action->isJoin());
	}

	public function testEnd()
	{
		$action = new Action();
		$action->setClassName("Foo");
		$action->setText("end");
		$action->parse();
		$this->assertFalse($action->getNext());
		$this->assertFalse($action->hasReturnValues());
	}

	public function testNextAlreadyDefined()
	{
		$action = new Action();
		$action->setClassName("Foo");
		$action->setText("end > action1");
		try {
			$action->parse();
		} catch (\Throwable $e) {
			$this->assertEquals("Next already defined.\nend >action1\n    ^\n", $e->getMessage());
		}
	}

	public function testNextAction()
	{
		$action = new Action();
		$action->setClassName("Foo");
		$action->setText("> action");
		$action->parse();
		$this->assertEquals("Foo::action", $action->getNext());
		$this->assertFalse($action->hasReturnValues());
	}

	public function testNextFork()
	{
		$action = new Action();
		$action->setClassName("Foo");
		$action->setText('> action1 | Bar::action2 | Foo\Bar::action3');
		$action->parse();
		$this->assertEquals(["Foo::action1", "Bar::action2", "Foo\Bar::action3"], $action->getNext());
		$this->assertFalse($action->hasReturnValues());
	}

	public function testNextSplit1()
	{
		$action = new Action();
		$action->setClassName("Foo");
		$action->setText('> rel:action1');
		$action->parse();
		$this->assertEquals(["rel"=>"Foo::action1"], $action->getNext());
		$this->assertFalse($action->hasReturnValues());
	}

	public function testNextSplitN()
	{
		$action = new Action();
		$action->setClassName("Foo");
		$action->setText('> next:action1 | prev:Bar::action2 | end:Foo\Bar::action3');
		$action->parse();
		$this->assertEquals(["next"=>"Foo::action1", "prev"=>"Bar::action2", "end"=>"Foo\Bar::action3"], $action->getNext());
		$this->assertFalse($action->hasReturnValues());
	}

	public function testBooleanReturnValues()
	{
		$action = new Action();
		$action->setClassName("Foo");
		$action->setText("=true end  =false > action2");
		$action->parse();
		$this->assertEquals((object)["true"=>false, "false"=>"Foo::action2"], $action->getNext());
		$this->assertTrue($action->hasReturnValues());
	}

	public function testIntegerReturnValues()
	{
		$action = new Action();
		$action->setClassName("Foo");
		$action->setText("=0 end  =1 > action2  =2 > action3  =3 > action4");
		$action->parse();
		$this->assertEquals((object)[0=>false, 1=>"Foo::action2", 2=>"Foo::action3", 3=>"Foo::action4"], $action->getNext());
		$this->assertTrue($action->hasReturnValues());
	}

	public function testTooFewReturnValues()
	{
		$action = new Action();
		$action->setClassName("Foo");
		$action->setText("=0 end");
		try {
			$action->parse();
			$this->fail();
		} catch (\Throwable $e) {
			$this->assertEquals("At least two next expressions are required when using return values.", $e->getMessage());
		}
	}

	public function testMixedReturnValues1()
	{
		$action = new Action();
		$action->setClassName("Foo");
		$action->setText("=0 end  =true >action2");
		try {
			$action->parse();
			$this->fail();
		} catch (\Throwable $e) {
			$this->assertEquals("When using boolean return values, you must declare a next expression for both true and false.", $e->getMessage());
		}
	}

	public function testMixedReturnValues2()
	{
		$action = new Action();
		$action->setClassName("Foo");
		$action->setText("=0 end  =true > action2  =false > action3");
		try {
			$action->parse();
			$this->fail();
		} catch (\Throwable $e) {
			$this->assertEquals("When using boolean return values, only two next expressions are allowed.", $e->getMessage());
		}
	}

	public function testNextForkReturnValues()
	{
		$action = new Action();
		$action->setClassName("Foo");
		$action->setText('=true > action1 | Bar::action2 | Foo\Bar::action3  =false end');
		$action->parse();
		$this->assertEquals((object)["true"=>["Foo::action1", "Bar::action2", "Foo\Bar::action3"], "false"=>false], $action->getNext());
		$this->assertTrue($action->hasReturnValues());
	}

	public function testNextSplitReturnValues()
	{
		$action = new Action();
		$action->setClassName("Foo");
		$action->setText('=true > rel1:action1 | rel2:Bar::action2 | rel3:Foo\Bar::action3  =false end');
		$action->parse();
		$this->assertEquals((object)["true"=>["rel1"=>"Foo::action1", "rel2"=>"Bar::action2", "rel3"=>"Foo\Bar::action3"], "false"=>false], $action->getNext());
		$this->assertTrue($action->hasReturnValues());
	}

	public function testDimensions()
	{
		$action = new Action();
		$action->setText('#foo=bar #baz= #bas ');
		$action->parse();
		$this->assertEquals(["foo"=>"bar", "baz"=>null, "bas"=>true], $action->getDimensions());
	}

	public function testMultilineInDocBlock()
	{
		$action = new Action();
		$action->setText("#foo=bar
			*    #baz=
			*    #bas
			* ");
		$action->parse();
		$this->assertEquals(["foo"=>"bar", "baz"=>null, "bas"=>true], $action->getDimensions());
	}

	public function testToString()
	{
		$action = Action::createFromText('[Foo::action1] > action2 #foo=bar #baz= #bas');
		$this->assertEquals('[Foo::action1] > Foo::action2 #foo=bar #baz= #bas', "$action");
	}
}
