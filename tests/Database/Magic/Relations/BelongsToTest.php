<?php
namespace Globalis\PuppetSkilled\Tests\Database\Magic\Relations;

use Mockery as m;
use Globalis\PuppetSkilled\Database\Magic\Relations\BelongsTo;

class BelongsToTest extends \PHPUnit\Framework\TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testUpdateMethodRetrievesModelAndUpdates()
    {
        $relation = $this->getRelation();
        $mock = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $mock->shouldReceive('fill')->once()->with(['attributes'])->andReturn($mock);
        $mock->shouldReceive('save')->once()->andReturn(true);
        $relation->getQuery()->shouldReceive('first')->once()->andReturn($mock);

        $this->assertTrue($relation->update(['attributes']));
    }

    public function testEagerConstraintsAreProperlyAdded()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->shouldReceive('whereIn')->once()->with('relation.id', ['foreign.value', 'foreign.value.two']);
        $models = [new BelongsToModelStub, new BelongsToModelStub, new AnotherBelongsToModelStub];
        $relation->addEagerConstraints($models);
    }

    public function testRelationIsProperlyInitialized()
    {
        $relation = $this->getRelation();
        $model = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $model->shouldReceive('setRelation')->once()->with('foo', null);
        $models = $relation->initRelation([$model], 'foo');

        $this->assertEquals([$model], $models);
    }

    public function testModelsAreProperlyMatchedToParents()
    {
        $relation = $this->getRelation();
        $result1 = m::mock('stdClass');
        $result1->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $result2 = m::mock('stdClass');
        $result2->shouldReceive('getAttribute')->with('id')->andReturn(2);
        $model1 = new BelongsToModelStub;
        $model1->foreign_key = 1;
        $model2 = new BelongsToModelStub;
        $model2->foreign_key = 2;
        $models = $relation->match([$model1, $model2], [$result1, $result2], 'foo');

        $this->assertEquals(1, $models[0]->foo->getAttribute('id'));
        $this->assertEquals(2, $models[1]->foo->getAttribute('id'));
    }

    public function testAssociateMethodSetsForeignKeyOnModel()
    {
        $parent = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');
        $relation = $this->getRelation($parent);
        $associate = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $associate->shouldReceive('getAttribute')->once()->with('id')->andReturn(1);
        $parent->shouldReceive('setAttribute')->once()->with('foreign_key', 1);
        $parent->shouldReceive('setRelation')->once()->with('relation', $associate);

        $relation->associate($associate);
    }

    public function testDissociateMethodUnsetsForeignKeyOnModel()
    {
        $parent = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');
        $relation = $this->getRelation($parent);
        $parent->shouldReceive('setAttribute')->once()->with('foreign_key', null);
        $parent->shouldReceive('setRelation')->once()->with('relation', null);
        $relation->dissociate();
    }

    public function testAssociateMethodSetsForeignKeyOnModelById()
    {
        $parent = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');
        $relation = $this->getRelation($parent);
        $parent->shouldReceive('setAttribute')->once()->with('foreign_key', 1);
        $relation->associate(1);
    }

    public function testDefaultEagerConstraintsWhenIncrementing()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->shouldReceive('whereIn')->once()->with('relation.id', m::mustBe([0]));
        $models = [new MissingBelongsToModelStub, new MissingBelongsToModelStub];
        $relation->addEagerConstraints($models);
    }

    public function testDefaultEagerConstraintsWhenIncrementingAndNonIntKeyType()
    {
        $relation = $this->getRelation(null, false, 'string');
        $relation->getQuery()->shouldReceive('whereIn')->once()->with('relation.id', m::mustBe([null]));
        $models = [new MissingBelongsToModelStub, new MissingBelongsToModelStub];
        $relation->addEagerConstraints($models);
    }

    public function testDefaultEagerConstraintsWhenNotIncrementing()
    {
        $relation = $this->getRelation(null, false);
        $relation->getQuery()->shouldReceive('whereIn')->once()->with('relation.id', m::mustBe([null]));
        $models = [new MissingBelongsToModelStub, new MissingBelongsToModelStub];
        $relation->addEagerConstraints($models);
    }

    protected function getRelation($parent = null, $incrementing = true, $keyType = 'int')
    {
        $builder = m::mock('\Globalis\PuppetSkilled\Database\Magic\Builder');
        $builder->shouldReceive('where')->with('relation.id', '=', 'foreign.value');
        $related = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $related->incrementing = $incrementing;
        $related->shouldReceive('getKeyType')->andReturn($keyType);
        $related->shouldReceive('getIncrementing')->andReturn($incrementing);
        $related->shouldReceive('getKeyName')->andReturn('id');
        $related->shouldReceive('getTable')->andReturn('relation');
        $builder->shouldReceive('getModel')->andReturn($related);
        $parent = $parent ?: new BelongsToModelStub;

        return new BelongsTo($builder, $parent, 'foreign_key', 'id', 'relation');
    }
}

class BelongsToModelStub extends \Globalis\PuppetSkilled\Database\Magic\Model
{
    public $foreign_key = 'foreign.value';
}

class AnotherBelongsToModelStub extends \Globalis\PuppetSkilled\Database\Magic\Model
{
    public $foreign_key = 'foreign.value.two';
}

class MissingBelongsToModelStub extends \Globalis\PuppetSkilled\Database\Magic\Model
{
    public $foreign_key;
}
