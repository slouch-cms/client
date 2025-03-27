<?php

namespace SlouchCMS\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use PDO;

class SlouchCMSController extends Controller
{
    public function index(Request $request) {
        return response()->json([
			'success' => true,
			'status'  => 200
        ], 200);
    }

      /**
	 * Return the structure of the database
     * @return JsonResponse 
     * @throws BindingResolutionException 
     */
    public function getDatabaseStructure() {

		$data = [];

		$tables = DB::select('SHOW TABLES');
        foreach ($tables as $table) {
			  /**
			 * The DB::select() above returns objects with a "Tables_in_ctrl_client" key,
			 * so we need to pull out the object value to get the actual table name
			 */
			$table_name = current(get_object_vars($table));
			/**
			 * Pull out the list of columns. I don't believe we can use bindings with SHOW TABLES, for some reason.			 
			 */
			$columns = DB::select("
							SHOW COLUMNS
							FROM {$table_name}
						"); 
			$data[$table_name] = $columns;
		}
		return response()->json($data, 200);
	}

	/**
	 * Get a list of records, which we can then display in a Tabulator table
	 * @param Request $request 
	 * @return JsonResponse|void 
	 * @throws ValidationException 
	 */
	public function getRecords(Request $request) {
		
		$validator = Validator::make($request->all(), [
			'table'  => 'required',
			'select' => 'required|array',
			'join'   => 'array',
			'filter' => 'array',
			'images' => 'array',
		]);

		if ($validator->fails()) {
			return response()->json($validator->errors(), 422);
		}

		$input = $validator->validated();

		/**
		 * TODO: Implement the Eloquent approach here too
		 */
		
		$selects = $input['select'] ?? [];
		Log::debug($selects);

		 $query = DB::table($input['table']);
		 foreach ($selects as $select) {	
			if (is_array($select)) {
				$query->addSelect(DB::raw(sprintf(					
					"JSON_REMOVE(
						JSON_OBJECT(
								IFNULL(%s, 'null__'),
								%s
						),
						'$.null__'
					) AS %s",
					$select['id'],
					$select['name'],
					$select['as']
				)));
			} else {		
				$query->addSelect($select);
			}
		 }
		 foreach ($input['join'] ?? [] as $join) {
			 $query->leftJoin(
				 $join['table'],
				 $join['foreign_column'],
				 '=',
				 $join['local_column'],
			 );
		 }
		 /**
		  * TODO... do we need to validate the filter here? To what extent?
		  */
		 if (!empty($input['filter'])) {
			$query->where($input['filter']['column'], $input['filter']['value']);
		 }
		 if (!empty($request->query('page'))) {
			$data  = $query->paginate(50);
		 } else {
			$data  = $query->get();
		 }


		/**
		 * Convert relative image paths to full URLs
		 */
		if ($images = $input['images'] ?? false) {
			$data->each(function ($item, $key) use ($images) {
				foreach ($images as $image) {
					/**
					 * Ignore the value if it's empty, or if it's already a full URL
					 */
					if (empty($item->{$image}) || filter_var($item->{$image}, FILTER_VALIDATE_URL)) {
						continue;
					}
					$item->{$image} = Storage::disk('public')->url($item->{$image});
				}
			});
		}

		if (count($data) == 0) {
			/**
			 * To return an empty array as JSON, convert [] to an object
			 * This is necessary so that the server can identify the response as JSON;
			 * otherwise we just pass back [], which isn't technically JSON
			 */			
			$data = (object)[];			
		 }
		 
		return response()->json($data);
	}

	/**
	 * Return data about a single object
     * @param Request $request 
     * @return mixed 
	 * @throws ValidationException 
     */
    public function getRecord(Request $request) {
		$validator = Validator::make($request->all(), [
			'table'     => 'required',
			'select'    => 'required|array',
			'join'      => 'sometimes|array',
			'object_id' => 'required|numeric',
			'images'    => 'sometimes|array',
		]);

		if ($validator->fails()) {
			return response()->json($validator->errors(), 422);
		}

		$input = $validator->validated();

		/**
		 * TODO: Implement the Eloquent approach here too
		 */

		 $query = DB::table($input['table']);
		 foreach ($input['select'] as $select) {
			 if (is_array($select)) {
				 $query->addSelect(DB::raw(sprintf(
					 "JSON_REMOVE(
						 JSON_OBJECTAGG(
							 IFNULL(%s, 'null__'),
							 %s
						 ),
						 '$.null__'
					 ) AS %s",
					 $select['id'],
					 $select['name'],
					 $select['as']
				 )));
			 } else {
				 $query->addSelect($select);
			 }
		 }
 
		 foreach ($input['join'] as $join) {
			 $query->leftJoin(
				 $join['table'],
				 $join['foreign_column'],
				 '=',
				 $join['local_column'],
			 );
		 }
 
		 $query->where(sprintf("%s.id", $input['table']), $input['object_id']);
		 $query->groupBy(sprintf("%s.id", $input['table']));
 
		 $data  = $query->first();

		/**
		 * Convert relative image paths to full URLs
		 * TODO: repetition here
		 */
		if ($images = $input['images'] ?? false) {
			
			foreach ($images as $image) {
				/**
				 * Ignore the value if it's empty, or if it's already a full URL
				 */
				if (empty($data->{$image}) || filter_var($data->{$image}, FILTER_VALIDATE_URL)) {
					continue;
				}
				$data->{$image} = Storage::disk('public')->url($data->{$image});
			}
		}

		/**
		 * Is this where we should convert the returned JSON into an array?
		*/
		foreach ($input['select'] as $select) {
			if (is_array($select)) {
				$data->{$select['as']} = json_decode($data->{$select['as']}, true);
			}
		}




		return response()->json($data);
	}

	/**
	 * Insert or update a single object
     * @param Request $request 
     * @return mixed 
	 * @throws ValidationException 
     */
    public function putRecord(Request $request) {

		$validator = Validator::make($request->all(), [
			'table'     => 'required',
			'data'      => 'required|array',
			'object_id' => 'nullable|numeric',
		]);

		if ($validator->fails()) {
			return response()->json($validator->errors(), 422);
		}

		$input = $validator->validated();

		/**		  	
			// Relationships are now built as follows:
			$api_data['relationships'][$field] = [
				'local_column'   => $relationship->localColumnJoinsToColumn->name,
				'foreign_column' => $relationship->foreignColumnJoinsToColumn->name,
				'relationship'   => $relationship->name,
				'table  '        => $relationship->getJoinTableName(),
				'values'         => []
			];       
			foreach ($value as $v) {
				$api_data['relationships'][$field]['values'][] = $v;
			}  			
		*/

		$columns       = $input['data']['columns'] ?? [];
		$relationships = $input['data']['relationships'] ?? [];
		$table         = $input['table'];
		$object_id     = $input['object_id'];
		
		/**
		 * We can update data via an Eloquent model, or using Query Builder
		 * If we have a class at (eg) App\Models\CMS\BlogCategory, then we use Eloquent
		 */
		$eloquent_class = $this->getEloquentClassFromTableName($table);
		if (class_exists($eloquent_class)) {		
			
			/**
			 * Ideally we'd only unguard a model if no guarded/fillable attributes are set
			 * However, I don't think it's possible to do this statically?
			 * We'll just unguard for now:
			 */
			if (!$eloquent_class::isUnguarded()) {
				$eloquent_class::unguard();
			}
			$object = $eloquent_class::updateOrCreate(
				['id' => $object_id],
				$columns
			);
			$object_id = $object->id;
			/**
			 * Loop through relationships here. If the model doesn't have relationships defined,
			 * I think we should revert to the Query Builder approach; it'll be a faff if every
			 * custom model needs to have all relationships configured.
			 **/
			foreach ($relationships as $relationship_name=>$relationship) {
				/**
				 * See above for the structure of the relationships array
				 * Note that the Eloquent approach just needs the relationship name
				 * and the value(s) ie IDs of the related objects
				 */				
				$values       = $relationship['values'];
				$relationship = Str::camel($relationship_name);
				if (method_exists($object, $relationship)) {					
					/**
					 * We can use a Reflection to work out if this is a has one or has many relationship
					 **/	
					$reflection        = new \ReflectionClass($object->$relationship());
					$relationship_type = $reflection->getShortName();
					/**
					 * We can also work out the class of the related object from the relationship
					 */
					$related_class = get_class($object->$relationship);
					if ($relationship_type == 'BelongsTo') {
						$related_object = $related_class::findOrFail(current($values));
						$object->$relationship()->associate($related_object);						
					} elseif ($relationship_type == 'BelongsToMany') {
						/**
						 * No need to load the related object here, as sync just accepts an array of values
						 */
						$object->$relationship()->sync($values);
					}
					$object->save();
					/**
					 * Remove this relationship from the array, as we've processed it
					 */
					unset($relationships[$relationship_name]);
				}
			}
			/**
			 * If we have any relationships that we haven't processed, then we need to use the Query Builder
			 */
			if (!empty($relationships)) {
				foreach ($relationships as $relationship_name=>$relationship) {
					/**
					 * Handle has_one relationships here;
					 * we run through has_many relationships separately
					 */
					if (empty($relationship['table'])) {
						$local_column           = $relationship['local_column'];
						/**
						 * $values only has one value here as this is a has_one relationship
						 **/
						$value                  = current($relationship['values']);
						// $columns[$local_column] = $value;
						DB::table($table)->where('id', $object_id)->update($local_column, $value);
						unset($relationships[$relationship_name]);
					}					
				}
			}

		} else {			
			/**
			 * Now... if (eg) a table has a blog_category_id column here, then we need to set it
			 * in the upsert in case it doesn't allow null values. So...
			 */
			foreach ($relationships as $relationship_name=>$relationship) {
				/**
				 * If we don't have a 'table' set, then this is a has_one relationship and not a has many
				 * So, copy the _id column in to the $columns array that we use on the upsert
				 */
				if (empty($relationship['table'])) {
					$local_column           = $relationship['local_column'];
					/**
					 * $values only has one value here as this is a has_one relationship
					 **/
					$value                  = current($relationship['values']);
					$columns[$local_column] = $value;
					unset($relationships[$relationship_name]);
				}
			}
			try {
				DB::table($table)->upsert(
					$columns + ['id' => $object_id],
					['id'],
					array_keys($columns)				
				);
				$object_id = DB::getPdo()->lastInsertId();
			} catch(\Illuminate\Database\QueryException $e){ 
				if ($e->getCode() == 23000) {
					$error = sprintf('Database error: %s. Consider making this field mandatory.', $e->errorInfo[2]);
				} else {
					$error = $e->getMessage(); 
				}
				
				$response = [
					'success'   => false,
					'error'     => $error,
					'object_id' => $object_id,
				];
			}
		}
		foreach ($relationships as $relationship_name=>$relationship) {	
			
			/**
			 * By this point, we've processed all the "has_one" relationships (either via Eloquent or Query Builder)
			 * and may have processed some has_many relationships via Eloquent too. Anything that remains unprocessed
			 * should be handle via Query Builder:
			 */
	
			$local_column   = $relationship['local_column'];
			$foreign_column = $relationship['foreign_column'];
			$table          = $relationship['table'];
			$values         = $relationship['values'];
			
			/**
			 * Delete any existing relationships
			 */
			DB::table($table)->where($local_column, '=', $object_id)->delete();		
			
			/**
			 * Insert the new ones
			 */
			foreach ($values as $value) {
				$query = DB::table($table);
				$query->insert([
					$local_column   => $object_id,
					$foreign_column => $value,
				]);
			}
		}

		/**
		 * If we've set a response already, assume it's an error
		 */
		if (!isset($response)) {
			$response = [
				'success'   => true,
				'object_id' => $object_id,
			];
		}
		return response()->json($response);
	}

	/**
	 * Delete a single object
     * @param Request $request 
     * @return mixed 
	 * @throws ValidationException 
     */
    public function deleteRecord(Request $request) {
		$validator = Validator::make($request->all(), [
			'table'     => 'required',
			'object_id' => 'required|numeric',
		]);

		if ($validator->fails()) {
			return response()->json($validator->errors(), 422);
		}

		$input = $validator->validated();

		/**
		 * TODO: Implement the Eloquent approach here too
		 */

		$query = DB::table($input['table']);
		$query->where(sprintf("%s.id", $input['table']), $input['object_id']);
		$query->delete();

		$response = [
			'success'   => true,			
		];
		return response()->json($response);
	}

	/**
	 * Reorder records
     * @param Request $request 
     * @return mixed 
	 * @throws ValidationException 
     */
    public function reorderRecords(Request $request) {
		$validator = Validator::make($request->all(), [
			'table'        => 'required',
			'order_column' => 'required',
			'data'         => 'required|array'
		]);

		if ($validator->fails()) {
			return response()->json($validator->errors(), 422);
		}

		$input = $validator->validated();

		/**
		 * TODO: Implement the Eloquent approach here too
		 */

		foreach ($input['data'] as $id=>$order) {
			$query = DB::table($input['table'])
						->where('id', $id)
						->update([$input['order_column'] => $order]);			
		}		

		$response = [
			'success'   => true,
			'object_id' => $input['object_id'] ?? DB::getPdo()->lastInsertId(),
		];
		return response()->json($response);
	}

	/**
	 * Given the name of a table, what's the corresponding CMS Eloquent class called? 
	 * @param mixed $table_name 
	 * @return string 
	 */
	protected function getEloquentClassFromTableName($table_name) {
		return sprintf('\App\Models\CMS\%s', Str::studly(Str::singular($table_name)));
	}
}
