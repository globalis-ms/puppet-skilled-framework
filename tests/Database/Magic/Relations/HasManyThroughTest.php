<?php
namespace Globalis\PuppetSkilled\Tests\Database\Magic\Relations;

use Mockery as m;
use Globalis\PuppetSkilled\Database\Magic\SoftDeletes;
use Globalis\PuppetSkilled\Database\Magic\Relations\HasManyThrough;

class HasManyThroughTest extends \PHPUnit\Framework\TestCase
{
    public function tearDown()
    {
        m::close();
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
        $relation->getQuery()->shouldReceive('whereIn')->once()->with('users.country_id', [1, 2]);
        $model1 = new HasManyThroughModelStub;
        $model1->id = 1;
        $model2 = new HasManyThroughModelStub;
        $model2->id = 2;
        $relation->addEagerConstraints([$model1, $model2]);
    }

    public function testEagerConstraintsAreProperlyAddedWithCustomKey()
    {
        $builder = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $builder->shouldReceive('join')->once()->with('users', 'users.id', '=', 'posts.user_id');
        $builder->shouldReceive('where')->with('users.country_id', '=', 1);

        $country = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $country->shouldReceive('getKeyName')->andReturn('id');
        $country->shouldReceive('offsetGet')->andReturn(1);
        $country->shouldReceive('getForeignKey')->andReturn('country_id');

        $user = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $user->shouldReceive('getTable')->andReturn('users');
        $user->shouldReceive('getQualifiedKeyName')->andReturn('users.id');
        $post = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $post->shouldReceive('getTable')->andReturn('posts');

        $builder->shouldReceive('getModel')->andReturn($post);

        $relation = new HasManyThrough($builder, $country, $user, 'country_id', 'user_id', 'not_id');
        $relation->getQuery()->shouldReceive('whereIn')->once()->with('users.country_id', [3, 4]);
        $model1 = new HasManyThroughModelStub;
        $model1->id = 1;
        $model1->not_id = 3;
        $model2 = new HasManyThroughModelStub;
        $model2->id = 2;
        $model2->not_id = 4;
        $relation->addEagerConstraints([$model1, $model2]);
    }

    public function testModelsAreProperlyMatchedToParents()
    {
        $relation = $this->getRelation();

        $result1 = new HasManyThroughModelStub;
        $result1->country_id = 1;
        $result2 = new HasManyThroughModelStub;
        $result2->country_id = 2;
        $result3 = new HasManyThroughModelStub;
        $result3->country_id = 2;

        $model1 = new HasManyThroughModelStub;
        $model1->id = 1;
        $model2 = new HasManyThroughModelStub;
        $model2->id = 2;
        $model3 = new HasManyThroughModelStub;
        $model3->id = 3;

        $models = $relation->match([$model1, $model2, $model3], [$result1, $result2, $result3], 'foo');

        $this->assertEquals(1, $models[0]->foo[0]->country_id);
        $this->assertEquals(1, count($models[0]->foo));
        $this->assertEquals(2, $models[1]->foo[0]->country_id);
        $this->assertEquals(2, $models[1]->foo[1]->country_id);
        $this->assertEquals(2, count($models[1]->foo));
        $this->assertEquals(0, count($models[2]->foo));
    }

    public function testModelsAreProperlyMatchedToParentsWithNonPrimaryKey()
    {
        $relation = $this->getRelationForNonPrimaryKey();

        $result1 = new HasManyThroughModelStub;
        $result1->country_id = 1;
        $result2 = new HasManyThroughModelStub;
        $result2->country_id = 2;
        $result3 = new HasManyThroughModelStub;
        $result3->country_id = 2;

        $model1 = new HasManyThroughModelStub;
        $model1->id = 1;
        $model2 = new HasManyThroughModelStub;
        $model2->id = 2;
        $model3 = new HasManyThroughModelStub;
        $model3->id = 3;

        $models = $relation->match([$model1, $model2, $model3], [$result1, $result2, $result3], 'foo');

        $this->assertEquals(1, $models[0]->foo[0]->country_id);
        $this->assertEquals(1, count($models[0]->foo));
        $this->assertEquals(2, $models[1]->foo[0]->country_id);
        $this->assertEquals(2, $models[1]->foo[1]->country_id);
        $this->assertEquals(2, count($models[1]->foo));
        $this->assertEquals(0, count($models[2]->foo));
    }

    public function testAllColumnsAreSelectedByDefault()
    {
        $select = ['posts.*', 'users.country_id'];

        $baseBuilder = m::mock('Globalis\PuppetSkilled\Database\Query\Builder');

        $relation = $this->getRelation();

        $builder = $relation->getQuery();
        $builder->shouldReceive('applyScopes')->andReturnSelf();
        $builder->shouldReceive('getQuery')->andReturn($baseBuilder);
        $builder->shouldReceive('addSelect')->once()->with($select)->andReturn($builder);
        $builder->shouldReceive('getModels')->once()->andReturn([]);

        $relation->get();
    }

    public function testOnlyProperColumnsAreSelectedIfProvided()
    {
        $select = ['users.country_id'];

        $baseBuilder = m::mock('Globalis\PuppetSkilled\Database\Query\Builder');
        $baseBuilder->columns = ['foo', 'bar'];

        $relation = $this->getRelation();

        $builder = $relation->getQuery();
        $builder->shouldReceive('applyScopes')->andReturnSelf();
        $builder->shouldReceive('getQuery')->andReturn($baseBuilder);
        $builder->shouldReceive('addSelect')->once()->with($select)->andReturn($builder);
        $builder->shouldReceive('getModels')->once()->andReturn([]);

        $relation->get();
    }

    public function testFirstMethod()
    {
        $relation = m::mock('Globalis\PuppetSkilled\Database\Magic\Relations\HasManyThrough[get]', $this->getRelationArguments());
        $relation->shouldReceive('get')->once()->andReturn(['first', 'second']);
        $relation->shouldReceive('take')->with(1)->once()->andReturn($relation);

        $this->assertEquals('first', $relation->first());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testFindOrFailThrowsException()
    {
        $relation = $this->getMockBuilder('Globalis\PuppetSkilled\Database\Magic\Relations\HasManyThrough')->setMethods(['find'])->setConstructorArgs($this->getRelationArguments())->getMock();
        $relation->expects($this->once())->method('find')->with('foo')->will($this->returnValue(null));

        try {
            $relation->findOrFail('foo');
        } catch (\Globalis\PuppetSkilled\Database\Magic\ModelNotFoundException $e) {
            $this->assertNotEmpty($e->getModel());

            throw $e;
        }
    }

    /**
     * @expectedException RuntimeException
     */
    public function testFirstOrFailThrowsException()
    {
        $relation = $this->getMockBuilder('Globalis\PuppetSkilled\Database\Magic\Relations\HasManyThrough')->setMethods(['first'])->setConstructorArgs($this->getRelationArguments())->getMock();
        $relation->expects($this->once())->method('first')->with(['id' => 'foo'])->will($this->returnValue(null));

        try {
            $relation->firstOrFail(['id' => 'foo']);
        } catch (\Globalis\PuppetSkilled\Database\Magic\ModelNotFoundException $e) {
            $this->assertNotEmpty($e->getModel());

            throw $e;
        }
    }

    public function testFindMethod()
    {
        $relation = m::mock('Globalis\PuppetSkilled\Database\Magic\Relations\HasManyThrough[first]', $this->getRelationArguments());
        $relation->shouldReceive('where')->with('posts.id', '=', 'foo')->once()->andReturn($relation);
        $relation->shouldReceive('first')->once()->andReturn(new \StdClass);

        $related = $relation->getRelated();
        $related->shouldReceive('getQualifiedKeyName')->once()->andReturn('posts.id');

        $relation->find('foo');
    }

    public function testFindManyMethod()
    {
        $relation = m::mock('Globalis\PuppetSkilled\Database\Magic\Relations\HasManyThrough[get]', $this->getRelationArguments());
        $relation->shouldReceive('get')->once()->andReturn(['first', 'second']);
        $relation->shouldReceive('whereIn')->with('posts.id', ['foo', 'bar'])->once()->andReturn($relation);

        $related = $relation->getRelated();
        $related->shouldReceive('getQualifiedKeyName')->once()->andReturn('posts.id');

        $relation->findMany(['foo', 'bar']);
    }

    public function testIgnoreSoftDeletingParent()
    {
        list($builder, $country, , $firstKey, $secondKey) = $this->getRelationArguments();
        $user = new HasManyThroughSoftDeletingModelStub;

        $builder->shouldReceive('whereNull')->with('users.deleted_at')->once()->andReturn($builder);

        $relation = new HasManyThrough($builder, $country, $user, $firstKey, $secondKey, 'id');
    }

    protected function getRelation()
    {
        list($builder, $country, $user, $firstKey, $secondKey, $overrideKey) = $this->getRelationArguments();

        return new HasManyThrough($builder, $country, $user, $firstKey, $secondKey, $overrideKey);
    }

    protected function getRelationForNonPrimaryKey()
    {
        list($builder, $country, $user, $firstKey, $secondKey, $overrideKey) = $this->getRelationArgumentsForNonPrimaryKey();

        return new HasManyThrough($builder, $country, $user, $firstKey, $secondKey, $overrideKey);
    }

    protected function getRelationArguments()
    {
        $builder = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $builder->shouldReceive('join')->once()->with('users', 'users.id', '=', 'posts.user_id');
        $builder->shouldReceive('where')->with('users.country_id', '=', 1);

        $country = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $country->shouldReceive('getKeyName')->andReturn('id');
        $country->shouldReceive('offsetGet')->andReturn(1);
        $country->shouldReceive('getForeignKey')->andReturn('country_id');
        $user = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $user->shouldReceive('getTable')->andReturn('users');
        $user->shouldReceive('getQualifiedKeyName')->andReturn('users.id');
        $post = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $post->shouldReceive('getTable')->andReturn('posts');

        $builder->shouldReceive('getModel')->andReturn($post);

        $user->shouldReceive('getKey')->andReturn(1);
        $user->shouldReceive('getCreatedAtColumn')->andReturn('created_at');
        $user->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');

        return [$builder, $country, $user, 'country_id', 'user_id', $country->getKeyName()];
    }

    protected function getRelationArgumentsForNonPrimaryKey()
    {
        $builder = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $builder->shouldReceive('join')->once()->with('users', 'users.id', '=', 'posts.user_id');
        $builder->shouldReceive('where')->with('users.country_id', '=', 1);

        $country = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $country->shouldReceive('offsetGet')->andReturn(1);
        $country->shouldReceive('getForeignKey')->andReturn('country_id');
        $user = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $user->shouldReceive('getTable')->andReturn('users');
        $user->shouldReceive('getQualifiedKeyName')->andReturn('users.id');
        $post = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $post->shouldReceive('getTable')->andReturn('posts');

        $builder->shouldReceive('getModel')->andReturn($post);

        $user->shouldReceive('getKey')->andReturn(1);
        $user->shouldReceive('getCreatedAtColumn')->andReturn('created_at');
        $user->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');

        return [$builder, $country, $user, 'country_id', 'user_id', 'other_id'];
    }
}

class HasManyThroughModelStub extends \Globalis\PuppetSkilled\Database\Magic\Model
{
    public $country_id = 'foreign.value';
}

class HasManyThroughSoftDeletingModelStub extends \Globalis\PuppetSkilled\Database\Magic\Model
{
    use SoftDeletes;
    public $table = 'users';
}
