<?php
namespace Globalis\PuppetSkilled\Tests\Database\Magic;

use Mockery as m;

class GlobalScopesTest extends \PHPUnit\Framework\TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testGlobalScopeIsApplied()
    {
        $model = new GlobalScopesTestModel();
        $query = $model->newQuery();
        $this->assertEquals('select * from `table` where `active` = ?', $query->toSql());
        $this->assertEquals([1], $query->getBindings());
    }

    public function testGlobalScopeCanBeRemoved()
    {
        $model = new GlobalScopesTestModel();
        $query = $model->newQuery()->withoutGlobalScope(ActiveScope::class);
        $this->assertEquals('select * from `table`', $query->toSql());
        $this->assertEquals([], $query->getBindings());
    }

    public function testClosureGlobalScopeIsApplied()
    {
        $model = new ClosureGlobalScopesTestModel();
        $query = $model->newQuery();
        $this->assertEquals('select * from `table` where `active` = ? order by `name` asc', $query->toSql());
        $this->assertEquals([1], $query->getBindings());
    }

    public function testClosureGlobalScopeCanBeRemoved()
    {
        $model = new ClosureGlobalScopesTestModel();
        $query = $model->newQuery()->withoutGlobalScope('active_scope');
        $this->assertEquals('select * from `table` order by `name` asc', $query->toSql());
        $this->assertEquals([], $query->getBindings());
    }

    public function testGlobalScopeCanBeRemovedAfterTheQueryIsExecuted()
    {
        $model = new ClosureGlobalScopesTestModel();
        $query = $model->newQuery();
        $this->assertEquals('select * from `table` where `active` = ? order by `name` asc', $query->toSql());
        $this->assertEquals([1], $query->getBindings());

        $query->withoutGlobalScope('active_scope');
        $this->assertEquals('select * from `table` order by `name` asc', $query->toSql());
        $this->assertEquals([], $query->getBindings());
    }

    public function testAllGlobalScopesCanBeRemoved()
    {
        $model = new ClosureGlobalScopesTestModel();
        $query = $model->newQuery()->withoutGlobalScopes();
        $this->assertEquals('select * from `table`', $query->toSql());
        $this->assertEquals([], $query->getBindings());

        $query = ClosureGlobalScopesTestModel::withoutGlobalScopes();
        $this->assertEquals('select * from `table`', $query->toSql());
        $this->assertEquals([], $query->getBindings());
    }

    public function testGlobalScopesWithOrWhereConditionsAreNested()
    {
        $model = new ClosureGlobalScopesWithOrTestModel();

        $query = $model->newQuery();
        $this->assertEquals('select `email`, `password` from `table` where (`email` = ? or `email` = ?) and `active` = ? order by `name` asc', $query->toSql());
        $this->assertEquals(['taylor@gmail.com', 'someone@else.com', 1], $query->getBindings());

        $query = $model->newQuery()->where('col1', 'val1')->orWhere('col2', 'val2');
        $this->assertEquals('select `email`, `password` from `table` where (`col1` = ? or `col2` = ?) and (`email` = ? or `email` = ?) and `active` = ? order by `name` asc', $query->toSql());
        $this->assertEquals(['val1', 'val2', 'taylor@gmail.com', 'someone@else.com', 1], $query->getBindings());
    }

    public function testRegularScopesWithOrWhereConditionsAreNested()
    {
        $query = ClosureGlobalScopesTestModel::withoutGlobalScopes()->where('foo', 'foo')->orWhere('bar', 'bar')->approved();

        $this->assertEquals('select * from `table` where (`foo` = ? or `bar` = ?) and (`approved` = ? or `should_approve` = ?)', $query->toSql());
        $this->assertEquals(['foo', 'bar', 1, 0], $query->getBindings());
    }

    public function testScopesStartingWithOrBooleanArePreserved()
    {
        $query = ClosureGlobalScopesTestModel::withoutGlobalScopes()->where('foo', 'foo')->orWhere('bar', 'bar')->orApproved();

        $this->assertEquals('select * from `table` where (`foo` = ? or `bar` = ?) or (`approved` = ? or `should_approve` = ?)', $query->toSql());
        $this->assertEquals(['foo', 'bar', 1, 0], $query->getBindings());
    }

    public function testHasQueryWhereBothModelsHaveGlobalScopes()
    {
        $query = GlobalScopesWithRelationModel::has('related')->where('bar', 'baz');

        $subQuery = 'select * from `table` where `table2`.`id` = `table`.`related_id` and `foo` = ? and `active` = ?';
        $mainQuery = 'select * from `table2` where exists ('.$subQuery.') and `bar` = ? and `active` = ? order by `name` asc';

        $this->assertEquals($mainQuery, $query->toSql());
        $this->assertEquals(['bar', 1, 'baz', 1], $query->getBindings());
    }
}

class ClosureGlobalScopesTestModel extends \Globalis\PuppetSkilled\Database\Magic\Model
{
    protected $table = 'table';

    public static function boot()
    {
        static::addGlobalScope(function ($query) {
            $query->orderBy('name');
        });

        static::addGlobalScope('active_scope', function ($query) {
            $query->where('active', 1);
        });

        parent::boot();
    }

    public function scopeApproved($query)
    {
        return $query->where('approved', 1)->orWhere('should_approve', 0);
    }

    public function scopeOrApproved($query)
    {
        return $query->orWhere('approved', 1)->orWhere('should_approve', 0);
    }
}

class GlobalScopesWithRelationModel extends ClosureGlobalScopesTestModel
{
    protected $table = 'table2';

    public function related()
    {
        return $this->hasMany(GlobalScopesTestModel::class, 'related_id')->where('foo', 'bar');
    }
}

class ClosureGlobalScopesWithOrTestModel extends ClosureGlobalScopesTestModel
{
    public static function boot()
    {
        static::addGlobalScope('or_scope', function ($query) {
            $query->where('email', 'taylor@gmail.com')->orWhere('email', 'someone@else.com');
        });

        static::addGlobalScope(function ($query) {
            $query->select('email', 'password');
        });

        parent::boot();
    }
}

class GlobalScopesTestModel extends \Globalis\PuppetSkilled\Database\Magic\Model
{
    protected $table = 'table';

    public static function boot()
    {
        static::addGlobalScope(new ActiveScope);

        parent::boot();
    }
}

class ActiveScope implements \Globalis\PuppetSkilled\Database\Magic\Scope
{
    public function apply(\Globalis\PuppetSkilled\Database\Magic\Builder $builder, \Globalis\PuppetSkilled\Database\Magic\Model $model)
    {
        return $builder->where('active', 1);
    }
}
