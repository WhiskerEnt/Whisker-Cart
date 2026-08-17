<?php
namespace Tests\Unit;

use Core\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    public function testRequiredFailsOnMissingEmptyAndEmptyArray(): void
    {
        $v = new Validator(['b' => '', 'c' => []], ['a' => 'required', 'b' => 'required', 'c' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertCount(3, $v->errors());
    }

    public function testRequiredPassesOnZeroString(): void
    {
        // "0" is PHP-falsy but a legitimate value — must not fail required.
        $v = new Validator(['a' => '0'], ['a' => 'required']);
        $this->assertTrue($v->passes());
    }

    public function testEmailRule(): void
    {
        $this->assertTrue((new Validator(['e' => 'a@b.com'], ['e' => 'email']))->passes());
        $this->assertTrue((new Validator(['e' => 'not-an-email'], ['e' => 'email']))->fails());
        // L13 regression: "0" must be validated (and rejected), not skipped.
        $this->assertTrue((new Validator(['e' => '0'], ['e' => 'email']))->fails());
        // Empty value passes email (only 'required' rejects empties).
        $this->assertTrue((new Validator(['e' => ''], ['e' => 'email']))->passes());
    }

    public function testNumericAndIntegerRules(): void
    {
        $this->assertTrue((new Validator(['n' => '12.5'], ['n' => 'numeric']))->passes());
        $this->assertTrue((new Validator(['n' => 'abc'], ['n' => 'numeric']))->fails());
        $this->assertTrue((new Validator(['n' => '12'], ['n' => 'integer']))->passes());
        $this->assertTrue((new Validator(['n' => '12.5'], ['n' => 'integer']))->fails());
    }

    public function testMinMaxOnStringsMeasuresLength(): void
    {
        $this->assertTrue((new Validator(['s' => 'ab'], ['s' => 'min:3']))->fails());
        $this->assertTrue((new Validator(['s' => 'abcd'], ['s' => 'min:3|max:10']))->passes());
        $this->assertTrue((new Validator(['s' => str_repeat('x', 11)], ['s' => 'max:10']))->fails());
    }

    public function testMinMaxOnNumericsMeasuresValue(): void
    {
        $this->assertTrue((new Validator(['n' => '5'], ['n' => 'min:10']))->fails());
        $this->assertTrue((new Validator(['n' => '50'], ['n' => 'min:10|max:100']))->passes());
        $this->assertTrue((new Validator(['n' => '500'], ['n' => 'max:100']))->fails());
    }

    public function testUrlSlugAndInRules(): void
    {
        $this->assertTrue((new Validator(['u' => 'https://x.com/a'], ['u' => 'url']))->passes());
        $this->assertTrue((new Validator(['u' => 'nope'], ['u' => 'url']))->fails());
        $this->assertTrue((new Validator(['s' => 'my-slug-1'], ['s' => 'slug']))->passes());
        $this->assertTrue((new Validator(['s' => 'My Slug!'], ['s' => 'slug']))->fails());
        $this->assertTrue((new Validator(['t' => 'b'], ['t' => 'in:a,b,c']))->passes());
        $this->assertTrue((new Validator(['t' => 'z'], ['t' => 'in:a,b,c']))->fails());
    }

    public function testFirstErrorReturnsAMessage(): void
    {
        $v = new Validator([], ['name' => 'required']);
        $this->assertSame('Name is required.', $v->firstError());
    }
}
