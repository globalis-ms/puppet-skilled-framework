<?php
namespace Globalis\PuppetSkilled\Tests\Database\Query;

use Mockery as m;
use Globalis\PuppetSkilled\Database\Query\Builder;
use Globalis\PuppetSkilled\Database\Query\Expression as Raw;

class QueryBuilderTest extends \PHPUnit\Framework\TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testBasicSelect()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $this->assertEquals('select * from "users"', $builder->toSql());
    }

    public function testBasicSelectWithGetColumns()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('query')->once()->andReturnUsing(function ($sql) {
            $this->assertEquals('select * from "users"', $sql);
            return m::self();
        });
        $builder->from('users')->get();
        $this->assertNull($builder->columns);

        $builder->getConnection()->shouldReceive('query')->once()->andReturnUsing(function ($sql) {
            $this->assertEquals('select "foo", "bar" from "users"', $sql);
            return m::self();
        });
        $builder->from('users')->get(['foo', 'bar']);
        $this->assertNull($builder->columns);

        $this->assertEquals('select * from "users"', $builder->toSql());
        $this->assertNull($builder->columns);
    }

    public function testBasicTableWrappingProtectsQuotationMarks()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('some"table');
        $this->assertEquals('select * from "some""table"', $builder->toSql());
    }

    public function testAliasWrappingAsWholeConstant()
    {
        $builder = $this->getBuilder();
        $builder->select('x.y as foo.bar')->from('baz');
        $this->assertEquals('select "x"."y" as "foo.bar" from "baz"', $builder->toSql());
    }

    public function testAddingSelects()
    {
        $builder = $this->getBuilder();
        $builder->select('foo')->addSelect('bar')->addSelect(['baz', 'boom'])->from('users');
        $this->assertEquals('select "foo", "bar", "baz", "boom" from "users"', $builder->toSql());
    }

    public function testBasicSelectWithPrefix()
    {
        $builder = $this->getBuilder();
        $builder->getGrammar()->setTablePrefix('prefix_');
        $builder->select('*')->from('users');
        $this->assertEquals('select * from "prefix_users"', $builder->toSql());
    }

    public function testBasicSelectDistinct()
    {
        $builder = $this->getBuilder();
        $builder->distinct()->select('foo', 'bar')->from('users');
        $this->assertEquals('select distinct "foo", "bar" from "users"', $builder->toSql());
    }

    public function testBasicAlias()
    {
        $builder = $this->getBuilder();
        $builder->select('foo as bar')->from('users');
        $this->assertEquals('select "foo" as "bar" from "users"', $builder->toSql());
    }

    public function testAliasWithPrefix()
    {
        $builder = $this->getBuilder();
        $builder->getGrammar()->setTablePrefix('prefix_');
        $builder->select('*')->from('users as people');
        $this->assertEquals('select * from "prefix_users" as "prefix_people"', $builder->toSql());
    }

    public function testJoinAliasesWithPrefix()
    {
        $builder = $this->getBuilder();
        $builder->getGrammar()->setTablePrefix('prefix_');
        $builder->select('*')->from('services')->join('translations AS t', 't.item_id', '=', 'services.id');
        $this->assertEquals('select * from "prefix_services" inner join "prefix_translations" as "prefix_t" on "prefix_t"."item_id" = "prefix_services"."id"', $builder->toSql());
    }

    public function testBasicTableWrapping()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('public.users');
        $this->assertEquals('select * from "public"."users"', $builder->toSql());
    }

    public function testWhenCallback()
    {
        $callback = function ($query) {
            return $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(true, $callback)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(false, $callback)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "email" = ?', $builder->toSql());
    }

    public function testWhenCallbackWithDefault()
    {
        $callback = function ($query) {
            return $query->where('id', '=', 1);
        };

        $default = function ($query) {
            return $query->where('id', '=', 2);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(true, $callback, $default)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(false, $callback, $default)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
        $this->assertEquals([0 => 2, 1 => 'foo'], $builder->getBindings());
    }

    public function testBasicWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $this->assertEquals('select * from "users" where "id" = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testMySqlWrappingProtectsQuotationMarks()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->From('some`table');
        $this->assertEquals('select * from `some``table`', $builder->toSql());
    }

    public function testDateBasedWheresAcceptsTwoArguments()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', 1);
        $this->assertEquals('select * from `users` where date(`created_at`) = ?', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', 1);
        $this->assertEquals('select * from `users` where day(`created_at`) = ?', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', 1);
        $this->assertEquals('select * from `users` where month(`created_at`) = ?', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereyear('created_at', 1);
        $this->assertEquals('select * from `users` where year(`created_at`) = ?', $builder->toSql());
    }

    public function testWhereDayMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1);
        $this->assertEquals('select * from `users` where day(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testWhereMonthMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5);
        $this->assertEquals('select * from `users` where month(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 5], $builder->getBindings());
    }

    public function testWhereYearMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014);
        $this->assertEquals('select * from `users` where year(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 2014], $builder->getBindings());
    }

    public function testWhereTimeMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '>=', '22:00');
        $this->assertEquals('select * from `users` where time(`created_at`) >= ?', $builder->toSql());
        $this->assertEquals([0 => '22:00'], $builder->getBindings());
    }

    public function testWhereBetweens()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereBetween('id', [1, 2]);
        $this->assertEquals('select * from "users" where "id" between ? and ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotBetween('id', [1, 2]);
        $this->assertEquals('select * from "users" where "id" not between ? and ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testBasicOrWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhere('email', '=', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? or "email" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testRawWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereRaw('id = ? or email = ?', [1, 'foo']);
        $this->assertEquals('select * from "users" where id = ? or email = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testRawOrWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereRaw('email = ?', ['foo']);
        $this->assertEquals('select * from "users" where "id" = ? or email = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testBasicWhereIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', [1, 2, 3]);
        $this->assertEquals('select * from "users" where "id" in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', [1, 2, 3]);
        $this->assertEquals('select * from "users" where "id" = ? or "id" in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 1, 2 => 2, 3 => 3], $builder->getBindings());
    }

    public function testBasicWhereNotIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', [1, 2, 3]);
        $this->assertEquals('select * from "users" where "id" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotIn('id', [1, 2, 3]);
        $this->assertEquals('select * from "users" where "id" = ? or "id" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 1, 2 => 2, 3 => 3], $builder->getBindings());
    }

    public function testRawWhereIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', [new Raw(1)]);
        $this->assertEquals('select * from "users" where "id" in (1)', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', [new Raw(1)]);
        $this->assertEquals('select * from "users" where "id" = ? or "id" in (1)', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testEmptyWhereIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', []);
        $this->assertEquals('select * from "users" where 0 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', []);
        $this->assertEquals('select * from "users" where "id" = ? or 0 = 1', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testEmptyWhereNotIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', []);
        $this->assertEquals('select * from "users" where 1 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotIn('id', []);
        $this->assertEquals('select * from "users" where "id" = ? or 1 = 1', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testBasicWhereColumn()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereColumn('first_name', 'last_name')->orWhereColumn('first_name', 'middle_name');
        $this->assertEquals('select * from "users" where "first_name" = "last_name" or "first_name" = "middle_name"', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereColumn('updated_at', '>', 'created_at');
        $this->assertEquals('select * from "users" where "updated_at" > "created_at"', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testArrayWhereColumn()
    {
        $conditions = [
            ['first_name', 'last_name'],
            ['updated_at', '>', 'created_at'],
        ];

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereColumn($conditions);
        $this->assertEquals('select * from "users" where ("first_name" = "last_name" and "updated_at" > "created_at")', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testUnions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $this->assertEquals('select * from "users" where "id" = ? union select * from "users" where "id" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getMySqlBuilder()->select('*')->from('users')->where('id', '=', 2));
        $this->assertEquals('(select * from `users` where `id` = ?) union (select * from `users` where `id` = ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getMysqlBuilder();
        $expectedSql = '(select `a` from `t1` where `a` = ? and `b` = ?) union (select `a` from `t2` where `a` = ? and `b` = ?) order by `a` asc limit 10';
        $union = $this->getMysqlBuilder()->select('a')->from('t2')->where('a', 11)->where('b', 2);
        $builder->select('a')->from('t1')->where('a', 10)->where('b', 1)->union($union)->orderBy('a')->limit(10);
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals([0 => 10, 1 => 1, 2 => 11, 3 => 2], $builder->getBindings());
    }

    public function testUnionAlls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $this->assertEquals('select * from "users" where "id" = ? union all select * from "users" where "id" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testMultipleUnions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 3));
        $this->assertEquals('select * from "users" where "id" = ? union select * from "users" where "id" = ? union select * from "users" where "id" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
    }

    public function testMultipleUnionAlls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 3));
        $this->assertEquals('select * from "users" where "id" = ? union all select * from "users" where "id" = ? union all select * from "users" where "id" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
    }

    public function testUnionOrderBys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->orderBy('id', 'desc');
        $this->assertEquals('select * from "users" where "id" = ? union select * from "users" where "id" = ? order by "id" desc', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testUnionLimitsAndOffsets()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $builder->union($this->getBuilder()->select('*')->from('dogs'));
        $builder->skip(5)->take(10);
        $this->assertEquals('select * from "users" union select * from "dogs" limit 10 offset 5', $builder->toSql());
    }

    public function testUnionWithJoin()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $builder->union($this->getBuilder()->select('*')->from('dogs')->join('breeds', function ($join) {
            $join->on('dogs.breed_id', '=', 'breeds.id')
                ->where('breeds.is_native', '=', 1);
        }));
        $this->assertEquals('select * from "users" union select * from "dogs" inner join "breeds" on "dogs"."breed_id" = "breeds"."id" and "breeds"."is_native" = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testMySqlUnionOrderBys()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getMySqlBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->orderBy('id', 'desc');
        $this->assertEquals('(select * from `users` where `id` = ?) union (select * from `users` where `id` = ?) order by `id` desc', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testMySqlUnionLimitsAndOffsets()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users');
        $builder->union($this->getMySqlBuilder()->select('*')->from('dogs'));
        $builder->skip(5)->take(10);
        $this->assertEquals('(select * from `users`) union (select * from `dogs`) limit 10 offset 5', $builder->toSql());
    }

    public function testSubSelectWhereIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', function ($q) {
            $q->select('id')->from('users')->where('age', '>', 25)->take(3);
        });
        $this->assertEquals('select * from "users" where "id" in (select "id" from "users" where "age" > ? limit 3)', $builder->toSql());
        $this->assertEquals([25], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', function ($q) {
            $q->select('id')->from('users')->where('age', '>', 25)->take(3);
        });
        $this->assertEquals('select * from "users" where "id" not in (select "id" from "users" where "age" > ? limit 3)', $builder->toSql());
        $this->assertEquals([25], $builder->getBindings());
    }

    public function testBasicWhereNulls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNull('id');
        $this->assertEquals('select * from "users" where "id" is null', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNull('id');
        $this->assertEquals('select * from "users" where "id" = ? or "id" is null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testBasicWhereNotNulls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotNull('id');
        $this->assertEquals('select * from "users" where "id" is not null', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '>', 1)->orWhereNotNull('id');
        $this->assertEquals('select * from "users" where "id" > ? or "id" is not null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testGroupBys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy('email');
        $this->assertEquals('select * from "users" group by "email"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy('id', 'email');
        $this->assertEquals('select * from "users" group by "id", "email"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy(['id', 'email']);
        $this->assertEquals('select * from "users" group by "id", "email"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy(new Raw('DATE(created_at)'));
        $this->assertEquals('select * from "users" group by DATE(created_at)', $builder->toSql());
    }

    public function testOrderBys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('email')->orderBy('age', 'desc');
        $this->assertEquals('select * from "users" order by "email" asc, "age" desc', $builder->toSql());

        $builder->orders = null;
        $this->assertEquals('select * from "users"', $builder->toSql());

        $builder->orders = [];
        $this->assertEquals('select * from "users"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('email')->orderByRaw('"age" ? desc', ['foo']);
        $this->assertEquals('select * from "users" order by "email" asc, "age" ? desc', $builder->toSql());
        $this->assertEquals(['foo'], $builder->getBindings());
    }

    public function testHavings()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->having('email', '>', 1);
        $this->assertEquals('select * from "users" having "email" > ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')
            ->orHaving('email', '=', 'test@example.com')
            ->orHaving('email', '=', 'test2@example.com');
        $this->assertEquals('select * from "users" having "email" = ? or "email" = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy('email')->having('email', '>', 1);
        $this->assertEquals('select * from "users" group by "email" having "email" > ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('email as foo_email')->from('users')->having('foo_email', '>', 1);
        $this->assertEquals('select "email" as "foo_email" from "users" having "foo_email" > ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select(['category', new Raw('count(*) as "total"')])->from('item')->where('department', '=', 'popular')->groupBy('category')->having('total', '>', new Raw('3'));
        $this->assertEquals('select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" > 3', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select(['category', new Raw('count(*) as "total"')])->from('item')->where('department', '=', 'popular')->groupBy('category')->having('total', '>', 3);
        $this->assertEquals('select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" > ?', $builder->toSql());
    }

    public function testHavingFollowedBySelectGet()
    {
        $builder = $this->getBuilder();
        $query = 'select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" > ?';
        $builder->getConnection()
            ->shouldReceive('query')
            ->once()
            ->with($query, ['popular', 3])
            ->andReturn($this->getResultMock([['category' => 'rock', 'total' => 5]]));
        $builder->from('item');
        $result = $builder->select(['category', new Raw('count(*) as "total"')])->where('department', '=', 'popular')->groupBy('category')->having('total', '>', 3)->get();
        $this->assertEquals([['category' => 'rock', 'total' => 5]], $result);

        // Using \Raw value
        $builder = $this->getBuilder();
        $query = 'select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" > 3';
        $builder->getConnection()
            ->shouldReceive('query')
            ->once()
            ->with($query, ['popular'])
            ->andReturn($this->getResultMock([['category' => 'rock', 'total' => 5]]));
        $builder->from('item');
        $result = $builder->select(['category', new Raw('count(*) as "total"')])->where('department', '=', 'popular')->groupBy('category')->having('total', '>', new Raw('3'))->get();
        $this->assertEquals([['category' => 'rock', 'total' => 5]], $result);
    }

    public function testRawHavings()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->havingRaw('user_foo < user_bar');
        $this->assertEquals('select * from "users" having user_foo < user_bar', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->having('baz', '=', 1)->orHavingRaw('user_foo < user_bar');
        $this->assertEquals('select * from "users" having "baz" = ? or user_foo < user_bar', $builder->toSql());
    }

    public function testLimitsAndOffsets()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->offset(5)->limit(10);
        $this->assertEquals('select * from "users" limit 10 offset 5', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->skip(5)->take(10);
        $this->assertEquals('select * from "users" limit 10 offset 5', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->skip(0)->take(0);
        $this->assertEquals('select * from "users" limit 0 offset 0', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->skip(-5)->take(-10);
        $this->assertEquals('select * from "users" offset 0', $builder->toSql());
    }

    public function testForPage()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(2, 15);
        $this->assertEquals('select * from "users" limit 15 offset 15', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(0, 15);
        $this->assertEquals('select * from "users" limit 15 offset 0', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(-2, 15);
        $this->assertEquals('select * from "users" limit 15 offset 0', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(2, 0);
        $this->assertEquals('select * from "users" limit 0 offset 0', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(0, 0);
        $this->assertEquals('select * from "users" limit 0 offset 0', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(-2, 0);
        $this->assertEquals('select * from "users" limit 0 offset 0', $builder->toSql());
    }

    public function testGetCountForPaginationWithBindings()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->selectSub(function ($q) {
            $q->select('body')->from('posts')->where('id', 4);
        }, 'post');

        $builder->getConnection()
            ->shouldReceive('query')
            ->once()
            ->with('select count(*) as aggregate from "users"', [])
            ->andReturn($this->getResultMock([['aggregate' => 1]]));

        $count = $builder->getCountForPagination();
        $this->assertEquals(1, $count);
        $this->assertEquals([4], $builder->getBindings());
    }

    public function testGetCountForPaginationWithColumnAliases()
    {
        $builder = $this->getBuilder();
        $columns = ['body as post_body', 'teaser', 'posts.created as published'];
        $builder->from('posts')->select($columns);

        $builder->getConnection()
            ->shouldReceive('query')
            ->once()
            ->with('select count("body", "teaser", "posts"."created") as aggregate from "posts"', [])
            ->andReturn($this->getResultMock([['aggregate' => 1]]));

        $count = $builder->getCountForPagination($columns);
        $this->assertEquals(1, $count);
    }

    public function testWhereShortcut()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhere('name', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? or "name" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testWhereWithArrayConditions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where([['foo', 1], ['bar', 2]]);
        $this->assertEquals('select * from "users" where ("foo" = ? and "bar" = ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where(['foo' => 1, 'bar' => 2]);
        $this->assertEquals('select * from "users" where ("foo" = ? and "bar" = ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where([['foo', 1], ['bar', '<', 2]]);
        $this->assertEquals('select * from "users" where ("foo" = ? and "bar" < ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testNestedWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', '=', 'foo')->orWhere(function ($q) {
            $q->where('name', '=', 'bar')->where('age', '=', 25);
        });
        $this->assertEquals('select * from "users" where "email" = ? or ("name" = ? and "age" = ?)', $builder->toSql());
        $this->assertEquals([0 => 'foo', 1 => 'bar', 2 => 25], $builder->getBindings());
    }

    public function testFullSubSelects()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', '=', 'foo')->orWhere('id', '=', function ($q) {
            $q->select(new Raw('max(id)'))->from('users')->where('email', '=', 'bar');
        });

        $this->assertEquals('select * from "users" where "email" = ? or "id" = (select max(id) from "users" where "email" = ?)', $builder->toSql());
        $this->assertEquals([0 => 'foo', 1 => 'bar'], $builder->getBindings());
    }

    public function testWhereExists()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->whereExists(function ($q) {
            $q->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'));
        });
        $this->assertEquals('select * from "orders" where exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->whereNotExists(function ($q) {
            $q->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'));
        });
        $this->assertEquals('select * from "orders" where not exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->where('id', '=', 1)->orWhereExists(function ($q) {
            $q->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'));
        });
        $this->assertEquals('select * from "orders" where "id" = ? or exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->where('id', '=', 1)->orWhereNotExists(function ($q) {
            $q->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'));
        });
        $this->assertEquals('select * from "orders" where "id" = ? or not exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());
    }

    public function testBasicJoins()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', 'users.id', 'contacts.id');
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->leftJoin('photos', 'users.id', '=', 'photos.id');
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" left join "photos" on "users"."id" = "photos"."id"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoinWhere('photos', 'users.id', '=', 'bar')->joinWhere('photos', 'users.id', '=', 'foo');
        $this->assertEquals('select * from "users" left join "photos" on "users"."id" = ? inner join "photos" on "users"."id" = ?', $builder->toSql());
        $this->assertEquals(['bar', 'foo'], $builder->getBindings());
    }

    public function testCrossJoins()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('sizes')->crossJoin('colors');
        $this->assertEquals('select * from "sizes" cross join "colors"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('tableB')->join('tableA', 'tableA.column1', '=', 'tableB.column2', 'cross');
        $this->assertEquals('select * from "tableB" cross join "tableA" on "tableA"."column1" = "tableB"."column2"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('tableB')->crossJoin('tableA', 'tableA.column1', '=', 'tableB.column2');
        $this->assertEquals('select * from "tableB" cross join "tableA" on "tableA"."column1" = "tableB"."column2"', $builder->toSql());
    }

    public function testComplexJoin()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orOn('users.name', '=', 'contacts.name');
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "users"."name" = "contacts"."name"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->where('users.id', '=', 'foo')->orWhere('users.name', '=', 'bar');
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = ? or "users"."name" = ?', $builder->toSql());
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());

        // Run the assertions again
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = ? or "users"."name" = ?', $builder->toSql());
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());
    }

    public function testJoinWhereNull()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNull('contacts.deleted_at');
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."deleted_at" is null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereNull('contacts.deleted_at');
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."deleted_at" is null', $builder->toSql());
    }

    public function testJoinWhereNotNull()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNotNull('contacts.deleted_at');
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."deleted_at" is not null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereNotNull('contacts.deleted_at');
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."deleted_at" is not null', $builder->toSql());
    }

    public function testJoinWhereIn()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."name" in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."name" in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());
    }

    public function testJoinWhereNotIn()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNotIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."name" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereNotIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."name" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());
    }

    public function testJoinsWithNestedConditions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->where(function ($j) {
                $j->where('contacts.country', '=', 'US')->orWhere('contacts.is_partner', '=', 1);
            });
        });
        $this->assertEquals('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and ("contacts"."country" = ? or "contacts"."is_partner" = ?)', $builder->toSql());
        $this->assertEquals(['US', 1], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->where('contacts.is_active', '=', 1)->orOn(function ($j) {
                $j->orWhere(function ($j) {
                    $j->where('contacts.country', '=', 'UK')->orOn('contacts.type', '=', 'users.type');
                })->where(function ($j) {
                    $j->where('contacts.country', '=', 'US')->orWhereNull('contacts.is_partner');
                });
            });
        });
        $this->assertEquals('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and "contacts"."is_active" = ? or (("contacts"."country" = ? or "contacts"."type" = "users"."type") and ("contacts"."country" = ? or "contacts"."is_partner" is null))', $builder->toSql());
        $this->assertEquals([1, 'UK', 'US'], $builder->getBindings());
    }

    public function testJoinsWithAdvancedConditions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')->where(function ($j) {
                $j->whereRole('admin')
                    ->orWhereNull('contacts.disabled')
                    ->orWhereRaw('year(contacts.created_at) = 2016');
            });
        });
        $this->assertEquals('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and ("role" = ? or "contacts"."disabled" is null or year(contacts.created_at) = 2016)', $builder->toSql());
        $this->assertEquals(['admin'], $builder->getBindings());
    }

    public function testRawExpressionsInSelect()
    {
        $builder = $this->getBuilder();
        $builder->select(new Raw('substr(foo, 6)'))->from('users');
        $this->assertEquals('select substr(foo, 6) from "users"', $builder->toSql());
    }

    public function testFindReturnsFirstResultByID()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('query')
            ->once()
            ->with('select * from "users" where "id" = ? limit 1', [1])
            ->andReturn($this->getResultMock([['foo' => 'bar']]));
        $results = $builder->from('users')->find(1);
        $this->assertEquals(['foo' => 'bar'], $results);
    }

    public function testFirstMethodReturnsFirstResult()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('query')
            ->once()
            ->with('select * from "users" where "id" = ? limit 1', [1])
            ->andReturn($this->getResultMock([['foo' => 'bar']]));
        $results = $builder->from('users')->where('id', '=', 1)->first();
        $this->assertEquals(['foo' => 'bar'], $results);
    }

    public function testValueMethodReturnsSingleColumn()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('query')
            ->once()
            ->with('select "foo" from "users" where "id" = ? limit 1', [1])
            ->andReturn($this->getResultMock([['foo' => 'bar']]));
        $results = $builder->from('users')->where('id', '=', 1)->value('foo');
        $this->assertEquals('bar', $results);
    }

    public function testAggregateFunctions()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('query')->once()->with('select count(*) as aggregate from "users"', [])
            ->andReturn($this->getResultMock([['aggregate' => 1]]));
        $results = $builder->from('users')->count();
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('query')
            ->once()
            ->with('select exists(select * from "users") as "exists"', [])
            ->andReturn($this->getResultMock([['exists' => 1]]));
        $results = $builder->from('users')->exists();
        $this->assertTrue($results);

        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('query')
            ->once()
            ->with('select max("id") as aggregate from "users"', [])
            ->andReturn($this->getResultMock([['aggregate' => 1]]));
        $results = $builder->from('users')->max('id');
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('query')
            ->once()
            ->with('select min("id") as aggregate from "users"', [])
            ->andReturn($this->getResultMock([['aggregate' => 1]]));
        $results = $builder->from('users')->min('id');
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('query')
            ->once()
            ->with('select sum("id") as aggregate from "users"', [])
            ->andReturn($this->getResultMock([['aggregate' => 1]]));
        $results = $builder->from('users')->sum('id');
        $this->assertEquals(1, $results);
    }

    public function testAggregateResetFollowedByGet()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('query')
            ->once()
            ->with('select count(*) as aggregate from "users"', [])
            ->andReturn($this->getResultMock([['aggregate' => 1]]));
        $builder->getConnection()->shouldReceive('query')
            ->once()
            ->with('select sum("id") as aggregate from "users"', [])
            ->andReturn($this->getResultMock([['aggregate' => 2]]));
        $builder->getConnection()->shouldReceive('query')
            ->once()
            ->with('select "column1", "column2" from "users"', [])
            ->andReturn($this->getResultMock([['column1' => 'foo', 'column2' => 'bar']]));
        $builder->from('users')->select('column1', 'column2');
        $count = $builder->count();
        $this->assertEquals(1, $count);
        $sum = $builder->sum('id');
        $this->assertEquals(2, $sum);
        $result = $builder->get();
        $this->assertEquals([['column1' => 'foo', 'column2' => 'bar']], $result);
    }

    public function testAggregateResetFollowedBySelectGet()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('query')
            ->once()
            ->with('select count("column1") as aggregate from "users"', [])
            ->andReturn($this->getResultMock([['aggregate' => 1]]));
        $builder->getConnection()
            ->shouldReceive('query')
            ->once()
            ->with('select "column2", "column3" from "users"', [])
            ->andReturn($this->getResultMock([['column2' => 'foo', 'column3' => 'bar']]));
        $builder->from('users');
        $count = $builder->count('column1');
        $this->assertEquals(1, $count);
        $result = $builder->select('column2', 'column3')->get();
        $this->assertEquals([['column2' => 'foo', 'column3' => 'bar']], $result);
    }

    public function testAggregateResetFollowedByGetWithColumns()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('query')
            ->once()
            ->with('select count("column1") as aggregate from "users"', [])
            ->andReturn($this->getResultMock([['aggregate' => 1]]));
        $builder->getConnection()
            ->shouldReceive('query')
            ->once()
            ->with('select "column2", "column3" from "users"', [])
            ->andReturn($this->getResultMock([['column2' => 'foo', 'column3' => 'bar']]));
        $builder->from('users');
        $count = $builder->count('column1');
        $this->assertEquals(1, $count);
        $result = $builder->get(['column2', 'column3']);
        $this->assertEquals([['column2' => 'foo', 'column3' => 'bar']], $result);
    }

    public function testAggregateWithSubSelect()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('result')
            ->once()
            ->andReturn([['aggregate' => 1]]);
        $builder->from('users')->selectSub(function ($query) {
            $query->from('posts')->select('foo')->where('title', 'foo');
        }, 'post');
        $count = $builder->count();
        $this->assertEquals(1, $count);
        $this->assertEquals(['foo'], $builder->getBindings());
    }

    public function testSubqueriesBindings()
    {
        $builder = $this->getBuilder();
        $second = $this->getBuilder()->select('*')->from('users')->orderByRaw('id = ?', 2);
        $third = $this->getBuilder()->select('*')->from('users')->where('id', 3)->groupBy('id')->having('id', '!=', 4);
        $builder->groupBy('a')->having('a', '=', 1)->union($second)->union($third);
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3, 3 => 4], $builder->getBindings());

        $builder = $this->getBuilder()->select('*')->from('users')->where('email', '=', function ($q) {
            $q->select(new Raw('max(id)'))
              ->from('users')->where('email', '=', 'bar')
              ->orderByRaw('email like ?', '%.com')
              ->groupBy('id')->having('id', '=', 4);
        })->orWhere('id', '=', 'foo')->groupBy('id')->having('id', '=', 5);
        $this->assertEquals([0 => 'bar', 1 => 4, 2 => '%.com', 3 => 'foo', 4 => 5], $builder->getBindings());
    }

    public function testInsertMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('query')->once()->with('insert into "users" ("email") values (?)', ['foo'])->andReturn(true);
        $result = $builder->from('users')->insert(['email' => 'foo']);
        $this->assertTrue($result);
    }

    public function testInsertGetIdMethod()
    {
        $builder = $this->getBuilder();
        $result = $builder->from('users')->insertGetId(['email' => 'foo'], 'id');
        $this->assertEquals(1, $result);
    }

    public function testInsertGetIdMethodRemovesExpressions()
    {
        $builder = $this->getBuilder();
        $result = $builder->from('users')->insertGetId(['email' => 'foo', 'bar' => new Raw('bar')], 'id');
        $this->assertEquals(1, $result);
    }

    public function testInsertMethodRespectsRawBindings()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('query')->once()->with('insert into "users" ("email") values (CURRENT TIMESTAMP)', [])->andReturn(true);
        $result = $builder->from('users')->insert(['email' => new Raw('CURRENT TIMESTAMP')]);
        $this->assertTrue($result);
    }

    public function testMultipleInsertsWithExpressionValues()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('query')->once()->with('insert into "users" ("email") values (UPPER(\'Foo\')), (LOWER(\'Foo\'))', [])->andReturn(true);
        $result = $builder->from('users')->insert([['email' => new Raw("UPPER('Foo')")], ['email' => new Raw("LOWER('Foo')")]]);
        $this->assertTrue($result);
    }

    public function testUpdateMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('query')->once()->with('update "users" set "email" = ?, "name" = ? where "id" = ?', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->where('id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->shouldReceive('query')->once()->with('update `users` set `email` = ?, `name` = ? where `id` = ? order by `foo` desc limit 5', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->where('id', '=', 1)->orderBy('foo', 'desc')->limit(5)->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethodWithJoins()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('query')->once()->with('update "users" inner join "orders" on "users"."id" = "orders"."user_id" set "email" = ?, "name" = ? where "users"."id" = ?', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('query')->once()->with('update "users" inner join "orders" on "users"."id" = "orders"."user_id" and "users"."id" = ? set "email" = ?, "name" = ?', [1, 'foo', 'bar'])->andReturn(1);
        $result = $builder->from('users')->join('orders', function ($join) {
            $join->on('users.id', '=', 'orders.user_id')
                ->where('users.id', '=', 1);
        })->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethodWithJoinsOnMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->shouldReceive('query')->once()->with('update `users` inner join `orders` on `users`.`id` = `orders`.`user_id` set `email` = ?, `name` = ? where `users`.`id` = ?', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->shouldReceive('query')->once()->with('update `users` inner join `orders` on `users`.`id` = `orders`.`user_id` and `users`.`id` = ? set `email` = ?, `name` = ?', [1, 'foo', 'bar'])->andReturn(1);
        $result = $builder->from('users')->join('orders', function ($join) {
            $join->on('users.id', '=', 'orders.user_id')
                ->where('users.id', '=', 1);
        })->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethodRespectsRaw()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('query')->once()->with('update "users" set "email" = foo, "name" = ? where "id" = ?', ['bar', 1])->andReturn(1);
        $result = $builder->from('users')->where('id', '=', 1)->update(['email' => new Raw('foo'), 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateOrInsertMethod()
    {
        $builder = m::mock('Globalis\PuppetSkilled\Database\Query\Builder[where,exists,insert]', [
            $this->getMockConnection(),
            new \Globalis\PuppetSkilled\Database\Query\Grammar,
        ]);

        $builder->shouldReceive('where')->once()->with(['email' => 'foo'])->andReturn(m::self());
        $builder->shouldReceive('exists')->once()->andReturn(false);
        $builder->shouldReceive('insert')->once()->with(['email' => 'foo', 'name' => 'bar'])->andReturn(true);

        $this->assertTrue($builder->updateOrInsert(['email' => 'foo'], ['name' => 'bar']));

        $builder = m::mock('Globalis\PuppetSkilled\Database\Query\Builder[where,exists,update]', [
            $this->getMockConnection(),
            new \Globalis\PuppetSkilled\Database\Query\Grammar,
        ]);

        $builder->shouldReceive('where')->once()->with(['email' => 'foo'])->andReturn(m::self());
        $builder->shouldReceive('exists')->once()->andReturn(true);
        $builder->shouldReceive('take')->andReturnSelf();
        $builder->shouldReceive('update')->once()->with(['name' => 'bar'])->andReturn(1);

        $this->assertTrue($builder->updateOrInsert(['email' => 'foo'], ['name' => 'bar']));
    }

    public function testDeleteMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('query')->once()->with('delete from "users" where "email" = ?', ['foo'])->andReturn(1);
        $result = $builder->from('users')->where('email', '=', 'foo')->delete();
        $this->assertEquals(1, $result);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('query')->once()->with('delete from "users" where "id" = ?', [1])->andReturn(1);
        $result = $builder->from('users')->delete(1);
        $this->assertEquals(1, $result);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->shouldReceive('query')->once()->with('delete from `users` where `email` = ? order by `id` asc limit 1', ['foo'])->andReturn(1);
        $result = $builder->from('users')->where('email', '=', 'foo')->orderBy('id')->take(1)->delete();
        $this->assertEquals(1, $result);
    }

    public function testDeleteWithJoinMethod()
    {
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->shouldReceive('query')->once()
            ->with('delete `users` from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` where `email` = ?', ['foo'])
            ->andReturn(1);
        $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->where('email', '=', 'foo')->orderBy('id')->limit(1)->delete();
        $this->assertEquals(1, $result);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->shouldReceive('query')->once()
            ->with('delete `users` from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` where `id` = ?', [1])
            ->andReturn(1);
        $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->orderBy('id')->take(1)->delete(1);
        $this->assertEquals(1, $result);
    }


    public function testMySqlWrapping()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users');
        $this->assertEquals('select * from `users`', $builder->toSql());
    }

    public function testMergeWheresCanMergeWheresAndBindings()
    {
        $builder = $this->getBuilder();
        $builder->wheres = ['foo'];
        $builder->mergeWheres(['wheres'], [12 => 'foo', 13 => 'bar']);
        $this->assertEquals(['foo', 'wheres'], $builder->wheres);
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());
    }

    public function testProvidingNullWithOperatorsBuildsCorrectly()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', null);
        $this->assertEquals('select * from "users" where "foo" is null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', '=', null);
        $this->assertEquals('select * from "users" where "foo" is null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', '!=', null);
        $this->assertEquals('select * from "users" where "foo" is not null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', '<>', null);
        $this->assertEquals('select * from "users" where "foo" is not null', $builder->toSql());
    }

    public function testDynamicWhere()
    {
        $method = 'whereFooBarAndBazOrQux';
        $parameters = ['corge', 'waldo', 'fred'];
        $builder = m::mock('Globalis\PuppetSkilled\Database\Query\Builder')->makePartial();

        $builder->shouldReceive('where')->with('foo_bar', '=', $parameters[0], 'and')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('baz', '=', $parameters[1], 'and')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('qux', '=', $parameters[2], 'or')->once()->andReturnSelf();

        $this->assertEquals($builder, $builder->dynamicWhere($method, $parameters));
    }

    public function testDynamicWhereIsNotGreedy()
    {
        $method = 'whereIosVersionAndAndroidVersionOrOrientation';
        $parameters = ['6.1', '4.2', 'Vertical'];
        $builder = m::mock('Globalis\PuppetSkilled\Database\Query\Builder')->makePartial();

        $builder->shouldReceive('where')->with('ios_version', '=', '6.1', 'and')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('android_version', '=', '4.2', 'and')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('orientation', '=', 'Vertical', 'or')->once()->andReturnSelf();

        $builder->dynamicWhere($method, $parameters);
    }

    public function testCallTriggersDynamicWhere()
    {
        $builder = $this->getBuilder();

        $this->assertEquals($builder, $builder->whereFooAndBar('baz', 'qux'));
        $this->assertCount(2, $builder->wheres);
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testBuilderThrowsExpectedExceptionWithUndefinedMethod()
    {
        $builder = $this->getBuilder();

        $builder->noValidMethodHere();
    }

    public function testMySqlLock()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock();
        $this->assertEquals('select * from `foo` where `bar` = ? for update', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock(false);
        $this->assertEquals('select * from `foo` where `bar` = ? lock in share mode', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());
    }

    public function testBindingOrder()
    {
        $expectedSql = 'select * from "users" inner join "othertable" on "bar" = ? where "registered" = ? group by "city" having "population" > ? order by match ("foo") against(?)';
        $expectedBindings = ['foo', 1, 3, 'bar'];

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('othertable', function ($join) {
            $join->where('bar', '=', 'foo');
        })->where('registered', 1)->groupBy('city')->having('population', '>', 3)->orderByRaw('match ("foo") against(?)', ['bar']);
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals($expectedBindings, $builder->getBindings());

        // order of statements reversed
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderByRaw('match ("foo") against(?)', ['bar'])->having('population', '>', 3)->groupBy('city')->where('registered', 1)->join('othertable', function ($join) {
            $join->where('bar', '=', 'foo');
        });
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals($expectedBindings, $builder->getBindings());
    }

    public function testAddBindingWithArrayMergesBindings()
    {
        $builder = $this->getBuilder();
        $builder->addBinding(['foo', 'bar']);
        $builder->addBinding(['baz']);
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testAddBindingWithArrayMergesBindingsInCorrectOrder()
    {
        $builder = $this->getBuilder();
        $builder->addBinding(['bar', 'baz'], 'having');
        $builder->addBinding(['foo'], 'where');
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testMergeBuilders()
    {
        $builder = $this->getBuilder();
        $builder->addBinding(['foo', 'bar']);
        $otherBuilder = $this->getBuilder();
        $otherBuilder->addBinding(['baz']);
        $builder->mergeBindings($otherBuilder);
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testMergeBuildersBindingOrder()
    {
        $builder = $this->getBuilder();
        $builder->addBinding('foo', 'where');
        $builder->addBinding('baz', 'having');
        $otherBuilder = $this->getBuilder();
        $otherBuilder->addBinding('bar', 'where');
        $builder->mergeBindings($otherBuilder);
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testSubSelect()
    {
        $expectedSql = 'select `foo`, `bar`, (select `baz` from `two` where `subkey` = ?) as `sub` from `one` where `key` = ?';
        $expectedBindings = ['subval', 'val'];

        $builder = $this->getMySqlBuilder();
        $builder->from('one')->select(['foo', 'bar'])->where('key', '=', 'val');
        $builder->selectSub(function ($query) {
            $query->from('two')->select('baz')->where('subkey', '=', 'subval');
        }, 'sub');
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals($expectedBindings, $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->from('one')->select(['foo', 'bar'])->where('key', '=', 'val');
        $subBuilder = $this->getMySqlBuilder();
        $subBuilder->from('two')->select('baz')->where('subkey', '=', 'subval');
        $builder->selectSub($subBuilder, 'sub');
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals($expectedBindings, $builder->getBindings());
    }

    public function testUppercaseLeadingBooleansAreRemoved()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'AND');
        $this->assertEquals('select * from "users" where "name" = ?', $builder->toSql());
    }

    public function testLowercaseLeadingBooleansAreRemoved()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'and');
        $this->assertEquals('select * from "users" where "name" = ?', $builder->toSql());
    }

    public function testCaseInsensitiveLeadingBooleansAreRemoved()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'And');
        $this->assertEquals('select * from "users" where "name" = ?', $builder->toSql());
    }

    public function testChunkWithLastChunkComplete()
    {
        $builder = $this->getMockQueryBuilder();
        $chunk1 = ['foo1', 'foo2'];
        $chunk2 = ['foo3', 'foo4'];
        $chunk3 = [];
        $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->once()->with(2, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->once()->with(3, 2)->andReturnSelf();
        $builder->shouldReceive('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

        $callbackAssertor = m::mock('StdClass');
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk3);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        });
    }

    public function testChunkWithLastChunkPartial()
    {
        $builder = $this->getMockQueryBuilder();
        $chunk1 = ['foo1', 'foo2'];
        $chunk2 = ['foo3'];
        $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->once()->with(2, 2)->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = m::mock('StdClass');
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        });
    }

    public function testChunkCanBeStoppedByReturningFalse()
    {
        $builder = $this->getMockQueryBuilder();
        $chunk1 = ['foo1', 'foo2'];
        $chunk2 = ['foo3'];
        $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->never()->with(2, 2);
        $builder->shouldReceive('get')->times(1)->andReturn($chunk1);

        $callbackAssertor = m::mock('StdClass');
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk2);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);

            return false;
        });
    }

    public function testChunkWithCountZero()
    {
        $builder = $this->getMockQueryBuilder();
        $chunk = [];
        $builder->shouldReceive('forPage')->once()->with(1, 0)->andReturnSelf();
        $builder->shouldReceive('get')->times(1)->andReturn($chunk);

        $callbackAssertor = m::mock('StdClass');
        $callbackAssertor->shouldReceive('doSomething')->never();

        $builder->chunk(0, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        });
    }

    public function testChunkPaginatesUsingIdWithLastChunkComplete()
    {
        $builder = $this->getMockQueryBuilder();
        $chunk1 = [(object) ['someIdField' => 1], (object) ['someIdField' => 2]];
        $chunk2 = [(object) ['someIdField' => 10], (object) ['someIdField' => 11]];
        $chunk3 = [];
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 11, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

        $callbackAssertor = m::mock('StdClass');
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk3);

        $builder->chunkById(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');
    }

    public function testChunkPaginatesUsingIdWithLastChunkPartial()
    {
        $builder = $this->getMockQueryBuilder();
        $chunk1 = [(object) ['someIdField' => 1], (object) ['someIdField' => 2]];
        $chunk2 = [(object) ['someIdField' => 10]];
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = m::mock('StdClass');
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);

        $builder->chunkById(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');
    }

    public function testChunkPaginatesUsingIdWithCountZero()
    {
        $builder = $this->getMockQueryBuilder();
        $chunk = [];
        $builder->shouldReceive('forPageAfterId')->once()->with(0, 0, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('get')->times(1)->andReturn($chunk);

        $callbackAssertor = m::mock('StdClass');
        $callbackAssertor->shouldReceive('doSomething')->never();

        $builder->chunkById(0, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');
    }

    public function testChunkPaginatesUsingIdWithAlias()
    {
        $builder = $this->getMockQueryBuilder();
        $chunk1 = [(object) ['table_id' => 1], (object) ['table_id' => 10]];
        $chunk2 = [];
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'table.id')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 10, 'table.id')->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = m::mock('StdClass');
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk2);

        $builder->chunkById(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'table.id', 'table_id');
    }

    protected function getBuilder()
    {
        $grammar = new \Globalis\PuppetSkilled\Database\Query\Grammar;

        return new Builder($this->getMockConnection(), $grammar);
    }

    protected function getMySqlBuilder()
    {
        $grammar = new \Globalis\PuppetSkilled\Database\Query\Grammar\MySqlGrammar;

        return new Builder($this->getMockConnection(), $grammar);
    }

    protected function getMySqlBuilderWithProcessor()
    {
        $grammar = new \Globalis\PuppetSkilled\Database\Query\Grammar\MySqlGrammar;

        return new Builder($this->getMockConnection(), $grammar);
    }
    /**
     * @return m\MockInterface
     */
    protected function getMockQueryBuilder()
    {
        $builder = m::mock('\Globalis\PuppetSkilled\Database\Query\Builder', [
            $this->getMockConnection(),
            new \Globalis\PuppetSkilled\Database\Query\Grammar,
        ])->makePartial();

        return $builder;
    }

    protected function getMockConnection()
    {
        $mock = m::mock('CI_DB_Driver');
        $mock->shouldReceive('result')
            ->andReturn([])
            ->byDefault();
        $mock->shouldReceive('query')
            ->andReturn(m::self())
            ->byDefault();
        $mock->shouldReceive('insert_id')
            ->andReturn(1)
            ->byDefault();
        return $mock;
    }

    protected function getResultMock($result)
    {
        $mock = m::mock('CI_DB_Result');
        $mock->shouldReceive('result')
            ->andReturn($result)
            ->byDefault();
        return $mock;
    }
}
