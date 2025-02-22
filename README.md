![CI](https://github.com/staudenmeir/laravel-adjacency-list/workflows/CI/badge.svg)
[![Code Coverage](https://scrutinizer-ci.com/g/staudenmeir/laravel-adjacency-list/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/staudenmeir/laravel-adjacency-list/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/staudenmeir/laravel-adjacency-list/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/staudenmeir/laravel-adjacency-list/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/staudenmeir/laravel-adjacency-list/v/stable)](https://packagist.org/packages/staudenmeir/laravel-adjacency-list)
[![Total Downloads](https://poser.pugx.org/staudenmeir/laravel-adjacency-list/downloads)](https://packagist.org/packages/staudenmeir/laravel-adjacency-list)
[![License](https://poser.pugx.org/staudenmeir/laravel-adjacency-list/license)](https://packagist.org/packages/staudenmeir/laravel-adjacency-list)

## Introduction
This Laravel Eloquent extension provides recursive relationships using common table expressions (CTE).

Supports Laravel 5.5.29+.

## Compatibility

- MySQL 8.0+
- MariaDB 10.2+
- PostgreSQL 9.4+
- SQLite 3.8.3+
- SQL Server 2008+
 
## Installation

    composer require staudenmeir/laravel-adjacency-list:"^1.0"

Use this command if you are in PowerShell on Windows (e.g. in VS Code):

    composer require staudenmeir/laravel-adjacency-list:"^^^^1.0"

## Usage

- [Getting Started](#getting-started)
- [Included Relationships](#included-relationships)
- [Custom Relationships](#custom-relationships)
- [Trees](#trees)
- [Filters](#filters)
- [Order](#order)
- [Depth](#depth)
- [Path](#path)
- [Custom Paths](#custom-paths)
- [Nested Results](#nested-results)

### Getting Started

Consider the following table schema for hierarchical data:

```php
Schema::create('users', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('parent_id')->nullable();
});
```

Use the `HasRecursiveRelationships` trait in your model to work with recursive relationships:

```php
class User extends Model
{
    use \Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;
}
```

By default, the trait expects a parent key named `parent_id`. You can customize it by overriding `getParentKeyName()`:

```php
class User extends Model
{
    use \Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;
    
    public function getParentKeyName()
    {
        return 'parent_id';
    }
}
```

By default, the trait uses the model's primary key as the local key. You can customize it by overriding `getLocalKeyName()`:

```php
class User extends Model
{
    use \Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;
    
    public function getLocalKeyName()
    {
        return 'id';
    }
}
```

### Included Relationships

The trait provides various relationships:

- `ancestors()`: The model's recursive parents.
- `ancestorsAndSelf()`: The model's recursive parents and itself.
- `children()`: The model's direct children.
- `childrenAndSelf()`: The model's direct children and itself.
- `descendants()`: The model's recursive children.
- `descendantsAndSelf()`: The model's recursive children and itself.
- `parent()`: The model's direct parent.
- `parentAndSelf()`: The model's direct parent and itself.
- `rootAncestor()`: The model's topmost parent.
- `siblings()`: The parent's other children.
- `siblingsAndSelf()`: All the parent's children.

```php
$ancestors = User::find($id)->ancestors;

$users = User::with('descendants')->get();

$users = User::whereHas('siblings', function ($query) {
    $query->where('name', '=', 'John');
})->get();

$total = User::find($id)->descendants()->count();

User::find($id)->descendants()->update(['active' => false]);

User::find($id)->siblings()->delete();
```

### Custom Relationships

You can also define custom relationships to retrieve related models recursively.

Consider a `HasMany` relationship between `User` and `Post`:
 
 ```php
 class User extends Model
 {
     public function posts()
     {
         return $this->hasMany('App\Post');
     }
 }
 ```

Define a `HasManyOfDescendants` relationship to get all posts of a user and its descendants:

```php
class User extends Model
{
    use \Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

    public function recursivePosts()
    {
        return $this->hasManyOfDescendantsAndSelf('App\Post');
    }
}

$recursivePosts = User::find($id)->recursivePosts;

$users = User::withCount('recursivePosts')->get();
```

Use `hasManyOfDescendants()` to only get the descendants' posts:

```php
class User extends Model
{
    use \Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

    public function descendantPosts()
    {
        return $this->hasManyOfDescendants('App\Post');
    }
}
```

If you are using the package outside of Laravel or have disabled package discovery for `staudenmeir/laravel-cte`, you need to add support for common table expressions to the related model:

```php
class Post extends Model
{
    use \Staudenmeir\LaravelCte\Eloquent\QueriesExpressions;
}
```

### Trees

The trait provides the `tree()` query scope to get all models, beginning at the root(s):

```php
$tree = User::tree()->get();
```

`treeOf()` allows you to query trees with custom constraints for the root model(s). Consider a table with multiple separate lists:

```php
$constraint = function ($query) {
    $query->whereNull('parent_id')->where('list_id', 1);
};

$tree = User::treeOf($constraint)->get();
```

### Filters

The trait provides query scopes to filter models by their position in the tree:

- `hasChildren()`: Models with children.
- `hasParent()`: Models with a parent.
- `isLeaf()`: Models without children.
- `isRoot()`: Models without a parent.

```php
$noLeaves = User::hasChildren()->get();

$noRoots = User::hasParent()->get();

$leaves = User::isLeaf()->get();

$roots = User::isRoot()->get();
```

### Order

The trait provides query scopes to order models breadth-first or depth-first:

- `breadthFirst()`: Get siblings before children.
- `depthFirst()`: Get children before siblings.

```php
$tree = User::tree()->breadthFirst()->get();

$descendants = User::find($id)->descendants()->depthFirst()->get();
```

### Depth

The results of ancestor, descendant and tree queries include an additional `depth` column.

It contains the model's depth *relative* to the query's parent. The depth is positive for descendants and negative for ancestors:

```php
$descendantsAndSelf = User::find($id)->descendantsAndSelf()->depthFirst()->get();

echo $descendantsAndSelf[0]->depth; // 0
echo $descendantsAndSelf[1]->depth; // 1
echo $descendantsAndSelf[2]->depth; // 2
```

You can customize the column name by overriding `getDepthName()`:

```php
class User extends Model
{
    use \Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

    public function getDepthName()
    {
        return 'depth';
    }
}
```

#### Depth Constraints

You can use the `whereDepth()` query scope to filter models by their relative depth:

```php
$descendants = User::find($id)->descendants()->whereDepth(2)->get();

$descendants = User::find($id)->descendants()->whereDepth('<', 3)->get();
```

Queries with `whereDepth()` constraints that limit the maximum depth still build the entire (sub)tree internally. Both tree scopes allow you to provide a maximum depth that improves query performance by only building the requested section of the tree:

```php
$tree = User::tree(3)->get();

$tree = User::treeOf($constraint, 3)->get();
```

### Path

The results of ancestor, descendant and tree queries include an additional `path` column.

It contains the dot-separated path of local keys from the query's parent to the model:

```php
$descendantsAndSelf = User::find(1)->descendantsAndSelf()->depthFirst()->get();

echo $descendantsAndSelf[0]->path; // 1
echo $descendantsAndSelf[1]->path; // 1.2
echo $descendantsAndSelf[2]->path; // 1.2.3
```

You can customize the column name and the separator by overriding the respective methods:

```php
class User extends Model
{
    use \Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

    public function getPathName()
    {
        return 'path';
    }

    public function getPathSeparator()
    {
        return '.';
    }
}
```

### Custom Paths

You can add custom path columns to the query results:

```php
class User extends Model
{
    use \Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

    public function getCustomPaths()
    {
        return [
            [
                'name' => 'slug_path',
                'column' => 'slug',
                'separator' => '/',
            ],
        ];
    }
}

$descendantsAndSelf = User::find(1)->descendantsAndSelf;

echo $descendantsAndSelf[0]->slug_path; // user-1
echo $descendantsAndSelf[1]->slug_path; // user-1/user-2
echo $descendantsAndSelf[2]->slug_path; // user-1/user-2/user-3
```

### Nested Results

Use the `toTree()` method on the result collection to generate a nested tree:

```php
$users = User::tree()->get();

$tree = $users->toTree();
```

This recursively sets `children` relationships:

```json
[
  {
    "id": 1,
    "children": [
      {
        "id": 2,
        "children": [
          {
            "id": 3,
            "children": []
          }
        ]
      },
      {
        "id": 4,
        "children": [
          {
            "id": 5,
            "children": []
          }
        ]
      }
    ]
  }
]
```

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CODE OF CONDUCT](.github/CODE_OF_CONDUCT.md) for details.
