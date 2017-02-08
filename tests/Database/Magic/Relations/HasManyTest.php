<?php
namespace Globalis\PuppetSkilled\Tests\Database\Magic\Relations;

use Mockery as m;
use Globalis\PuppetSkilled\Database\Magic\Relations\HasMany;

class HasManyTest extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testCreateMethodProperlyCreatesNewModel()
    {
        $relation = $this->getRelation();
        $created = $this->getMockBuilder('\Globalis\PuppetSkilled\Database\Magic\Model')->setMethods(['save', 'getKey', 'setAttribute'])->getMock();
        $created->expects($this->once())->method('save')->will($this->returnValue(true));
        $relation->getRelated()->shouldReceive('newInstance')->once()->with(['name' => 'taylor'])->andReturn($created);
        $created->expects($this->once())->method('setAttribute')->with('foreign_key', 1);

        $this->assertEquals($created, $relation->create(['name' => 'taylor']));
    }

    public function testFindOrNewMethodFindsModel()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->shouldReceive('find')->once()->with('foo', ['*'])->andReturn($model = m::mock('StdClass'));
        $model->shouldReceive('setAttribute')->never();

        $this->assertInstanceOf(\StdClass::class, $relation->findOrNew('foo'));
    }

    public function testFindOrNewMethodReturnsNewModelWithForeignKeySet()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->shouldReceive('find')->once()->with('foo', ['*'])->andReturn(null);
        $relation->getRelated()->shouldReceive('newInstance')->once()->with()->andReturn($model = m::mock('StdClass'));
        $model->shouldReceive('setAttribute')->once()->with('foreign_key', 1);

        $this->assertInstanceOf(\StdClass::class, $relation->findOrNew('foo'));
    }

    public function testFirstOrNewMethodFindsFirstModel()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
        $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock('StdClass'));
        $model->shouldReceive('setAttribute')->never();

        $this->assertInstanceOf(\StdClass::class, $relation->firstOrNew(['foo']));
    }

    public function testFirstOrNewMethodReturnsNewModelWithForeignKeySet()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
        $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn(null);
        $relation->getRelated()->shouldReceive('newInstance')->once()->with(['foo'])->andReturn($model = m::mock('StdClass'));
        $model->shouldReceive('setAttribute')->once()->with('foreign_key', 1);

        $this->assertInstanceOf(\StdClass::class, $relation->firstOrNew(['foo']));
    }

    public function testFirstOrCreateMethodFindsFirstModel()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
        $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock('StdClass'));
        $relation->getRelated()->shouldReceive('newInstance')->never();
        $model->shouldReceive('setAttribute')->never();
        $model->shouldReceive('save')->never();

        $this->assertInstanceOf(\StdClass::class, $relation->firstOrCreate(['foo']));
    }

    public function testFirstOrCreateMethodCreatesNewModelWithForeignKeySet()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
        $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn(null);
        $relation->getRelated()->shouldReceive('newInstance')->once()->with(['foo'])->andReturn($model = m::mock('StdClass'));
        $model->shouldReceive('save')->once()->andReturn(true);
        $model->shouldReceive('setAttribute')->once()->with('foreign_key', 1);

        $this->assertInstanceOf(\StdClass::class, $relation->firstOrCreate(['foo']));
    }

    public function testUpdateOrCreateMethodFindsFirstModelAndUpdates()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
        $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock('StdClass'));
        $relation->getRelated()->shouldReceive('newInstance')->never();
        $model->shouldReceive('fill')->once()->with(['bar']);
        $model->shouldReceive('save')->once();

        $this->assertInstanceOf(\StdClass::class, $relation->updateOrCreate(['foo'], ['bar']));
    }

    public function testUpdateOrCreateMethodCreatesNewModelWithForeignKeySet()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
        $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn(null);
        $relation->getRelated()->shouldReceive('newInstance')->once()->with(['foo'])->andReturn($model = m::mock('StdClass'));
        $model->shouldReceive('save')->once()->andReturn(true);
        $model->shouldReceive('fill')->once()->with(['bar']);
        $model->shouldReceive('setAttribute')->once()->with('foreign_key', 1);

        $this->assertInstanceOf(\StdClass::class, $relation->updateOrCreate(['foo'], ['bar']));
    }

    public function testUpdateMethodUpdatesModelsWithTimestamps()
    {
        $relation = $this->getRelation();
        $relation->getRelated()->shouldReceive('usesTimestamps')->once()->andReturn(true);
        $relation->getRelated()->shouldReceive('freshTimestampString')->once()->andReturn(100);
        $relation->getRelated()->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');
        $relation->getQuery()->shouldReceive('update')->once()->with(['foo' => 'bar', 'updated_at' => 100])->andReturn('results');

        $this->assertEquals('results', $relation->update(['foo' => 'bar']));
    }

    public function testRelationIsProperlyInitialized()
    {
        $relation = $this->getRelation();
        $model = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $model->shouldReceive('setRelation')->once()->with('foo', m::type('array'));
        $models = $relation->initRelation([$model], 'foo');

        $this->assertEquals([$model], $models);
    }

    public function testEagerConstraintsAreProperlyAdded()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->shouldReceive('whereIn')->once()->with('table.foreign_key', [1, 2]);
        $model1 = new HasManyModelStub;
        $model1->id = 1;
        $model2 = new HasManyModelStub;
        $model2->id = 2;
        $relation->addEagerConstraints([$model1, $model2]);
    }

    public function testModelsAreProperlyMatchedToParents()
    {
        $relation = $this->getRelation();

        $result1 = new HasManyModelStub;
        $result1->foreign_key = 1;
        $result2 = new HasManyModelStub;
        $result2->foreign_key = 2;
        $result3 = new HasManyModelStub;
        $result3->foreign_key = 2;

        $model1 = new HasManyModelStub;
        $model1->id = 1;
        $model2 = new HasManyModelStub;
        $model2->id = 2;
        $model3 = new HasManyModelStub;
        $model3->id = 3;

        $models = $relation->match([$model1, $model2, $model3], [$result1, $result2, $result3], 'foo');

        $this->assertEquals(1, $models[0]->foo[0]->foreign_key);
        $this->assertEquals(1, count($models[0]->foo));
        $this->assertEquals(2, $models[1]->foo[0]->foreign_key);
        $this->assertEquals(2, $models[1]->foo[1]->foreign_key);
        $this->assertEquals(2, count($models[1]->foo));
        $this->assertEquals(0, count($models[2]->foo));
    }

    protected function getRelation()
    {
        $builder = m::mock('\Globalis\PuppetSkilled\Database\Magic\Builder');
        $builder->shouldReceive('whereNotNull')->with('table.foreign_key');
        $builder->shouldReceive('where')->with('table.foreign_key', '=', 1);
        $related = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $builder->shouldReceive('getModel')->andReturn($related);
        $parent = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $parent->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $parent->shouldReceive('getCreatedAtColumn')->andReturn('created_at');
        $parent->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');

        return new HasMany($builder, $parent, 'table.foreign_key', 'id');
    }
}

class HasManyModelStub extends \Globalis\PuppetSkilled\Database\Magic\Model
{
    public $foreign_key = 'foreign.value';
}
