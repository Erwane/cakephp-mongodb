<?php
declare(strict_types=1);

namespace CakeMongo\Database;

use Cake\Database\Query;
use Cake\Database\QueryCompiler as DbQueryCompiler;
use Cake\Database\ValueBinder;

class QueryCompiler extends DbQueryCompiler
{
    public function build(Query $query, ValueBinder $binder): array
    {
        $out = [];

        $type = $query->type();

        $pipeline = [];
        $projects = [];

        foreach ($query->clause('select') as $alias => $field) {
            [$model, $field] = explode('.', $field);
            $projects[$alias] = '$' . $field;
        }

        if ($projects) {
            $pipeline[] = ['$project' => $projects];
        }

        // $query->traverseParts(
        //     $this->_sqlCompiler($sql, $query, $binder),
        //     $this->{"_{$type}Parts"}
        // );
        //
        // // Propagate bound parameters from sub-queries if the
        // // placeholders can be found in the SQL statement.
        // if ($query->getValueBinder() !== $binder) {
        //     foreach ($query->getValueBinder()->bindings() as $binding) {
        //         $placeholder = ':' . $binding['placeholder'];
        //         if (preg_match('/' . $placeholder . '(?:\W|$)/', $sql) > 0) {
        //             $binder->bind($placeholder, $binding['value'], $binding['type']);
        //         }
        //     }
        // }

        return $pipeline;
    }
}
