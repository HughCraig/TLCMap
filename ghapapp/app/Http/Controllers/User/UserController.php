<?php

namespace TLCMap\Http\Controllers\User;
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size','10M');
use TLCMap\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use TLCMap\Models\User;
use TLCMap\Models\Role;
use TLCMap\Models\SavedSearch;
use TLCMap\Models\Dataset;
use TLCMap\Models\Dataitem;
use TLCMap\Models\SubjectKeyword;
use TLCMap\Models\Register;
use TLCMap\Models\RecordType;

use TLCMap\Mail\EmailChangedOld;
use TLCMap\Mail\EmailChangedNew;
use TLCMap\Mail\EmailChangedWebmaster;
use TLCMap\Mail\PasswordChanged;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

use TLCMap\Http\Helpers\GeneralFunctions;

class UserController extends Controller {
  /*
  |--------------------------------------------------------------------------
  | User Controller
  |--------------------------------------------------------------------------
  |
  |
  */

  public $dateheadings = [
    ["datestart","dateend"],
    ["begin","end"],
    ["startdate","enddate"],
    ["start date","end date"],
    ["date start","date end"],
    ["date","date"] // if there is a single date set begin and end to same
  ];

  public $llheadings = [
      ["latitude","longitude"],
      ["lat","long"],
      ["lat","lng"]
  ];

  public function __construct()
  {
    $this->middleware('auth');
    $this->middleware('verified');
  }

  public function userProfile(Request $request)
  {
    return view('user.userprofile');
  }

  public function editUserPage(Request $request) {
    return view('user.edituser');
  }

  public function editUserInfo(Request $request) {
    $user = auth()->user();
    $input = $request->all();
    $rules =  [ 'name' => ['required', 'string', 'max:255'] ];

    $this->validate($request, $rules);

    $user->update(['name' => $request->input('name')]);
    return redirect('myprofile')->with('success', 'Profile updated!');
  }

  public function editUserPassword(Request $request) {
    $user = auth()->user();
    $notin = array_merge(explode(' ',strtolower($user->name)), explode('@',strtolower($user->email))); //cannot match username, or any part of the email address
    $rules = [ //rules for the validator
      'old_password' => function($attribute, $value, $fail) { //custom rule to check hashed passwords match
        if (! Hash::check($value, auth()->user()->password)) {
          $fail('Your current password doesnt match our database!'); //custom fail message
        }
      },
      'password' => [
        'required', 'string', 'min:8', 'max:16', 'confirmed', //10+ chars, must match the password-confirm box
        'regex:/[a-z]/','regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[^A-Za-z0-9]/', //must contain 1 of each: lowercase uppercase number and special character
        'not_regex:/(.)\1{4,}/', //must not contain any repeating char 4 or more times
        function($attribute, $value, $fail) use ($notin) {
          $v = strtolower($value);
          foreach($notin as $n) {
            if (strpos($v,$n) !== false) $fail('Password cannot contain any part of your name or email!');
          }
        }
      ], 
    ];

    $validator = Validator::make($request->all(),$rules); //create the validator

    if ($validator->fails()) return redirect()->back()->withErrors($validator->errors()); //if fails redirect back with errors

    $user->update(['password' => Hash::make($request->input('password'))]);

    //Email user
    Mail::to($user->email)->send(new PasswordChanged($user->name));

    return redirect('myprofile')->with('success', 'Password updated!'); //if input passes validation redirect with success message

  }

  public function editUserEmail(Request $request) {
    $user = auth()->user();

    $rules = [ //rules for the validator
      'emailpassword' => function($attribute, $value, $fail) { //custom rule to check hashed passwords match
        if (! Hash::check($value, auth()->user()->password)) {
          $fail('Incorrect password!'); //custom fail message
        }
      },
      'email' => [
        function($attribute, $value, $fail) {
          if (User::find($value)) $fail('A user with this email already exists!');
        },
        'required','email','confirmed']
    ];
    
    $validator = Validator::make($request->all(),$rules); //create the validator
    if ($validator->fails()) return redirect()->back()->withErrors($validator->errors()); //if fails redirect back with errors, else continue

    //vars for emails
    $old_email = $user->email;
    $new_email = $request->input('email');

    //WE NO LONGER NEED TO UPDATE PIVOT TABLES AS EMAIL IS NO LONGER THE PK OF THE USER TABLE!
      //UPDATE user_dataset pivot table to change all cases of old email to new email
      //$user->datasets()->newPivotStatement()->where('user_email', '=', $user->email)->update(array('user_email' => $request->input('email')));

      //UPDATE role_user pivot table to change all cases of old email to new email 
      //$user->roles()->newPivotStatement()->where('user_email', '=', $user->email)->update(array('user_email' => $request->input('email')));

    //UPDATE user email
    $user->update(['email' => $request->input('email')]);

    //Send notification emails to old, new , and webmaster
    Mail::to($old_email)->send(new EmailChangedOld($old_email, $new_email));
    Mail::to($new_email)->send(new EmailChangedNew($old_email, $new_email));
    Mail::to(env('WEBMASTER_EMAIL'))->send(new EmailChangedWebmaster($old_email, $new_email));
    
    return redirect('myprofile')->with('success', 'Email updated!'); //if input passes validation redirect with success message
  }

  public function userDatasets(Request $request)
  {
    return view('user.userdatasets',['user' => auth()->user()]);
  }

  public function userViewDataset(Request $request, int $id) {
    $user = auth()->user();
    $dataset = $user->datasets()->find($id);
    if (!$dataset) return redirect('myprofile/mydatasets');




    //lgas from DB
    $lgas = DB::table('gazetteer.register')->select('lga_name')->distinct()->where('lga_name', '<>', '')->get()->toArray();
    $temp = array();
    foreach($lgas as $row) {
        $temp[] = $row->lga_name;
    }
    $lgas = json_encode($temp, JSON_NUMERIC_CHECK);

    //feature_codes from DB
    $feature_terms = DB::table('gazetteer.register')->select('feature_term')->distinct()->where('feature_term', '<>', '')->get()->toArray();
    $temp = array();
    foreach($feature_terms as $row) {
        $temp[] = $row->feature_term;
    }
    $feature_terms = json_encode($temp, JSON_NUMERIC_CHECK);

    //parishes from DB
    $parishes = DB::table('gazetteer.register')->select('parish')->distinct()->where('parish', '<>', '')->get()->toArray();
    $temp = array();
    foreach($parishes as $row) {
        $temp[] = $row->parish;
    }
    $parishes = json_encode($temp, JSON_NUMERIC_CHECK);

    $states = DB::table('gazetteer.register')->select('state_code')->distinct()->groupby('state_code')->get();

    // recordtypes from db. Note that the DS and the Item both have a recordtype attribute
    $recordtypes = RecordType::types();
    if ($dataset->recordtype_id === null) {
      $dataset->recordtype_id = 1;
    }

    return view('user.userviewdataset',['lgas' => $lgas, 'feature_terms' => $feature_terms, 'parishes' => $parishes, 'states' => $states, 'recordtypes' => $recordtypes, 'ds' => $dataset, 'user' => auth()->user()]);
  }

  public function userSavedSearches(Request $request)
  {
    $user = auth()->user();
    $searches = SavedSearch::where('user_id',$user->id)->get();
    return view('user.usersavedsearches',['searches' => $searches]);
  }

  /*
    userDeleteSearches moved to AJAX CONTROLLER
  */

  public function newDatasetPage(Request $request)
  {
    $recordtypes = RecordType::types();
    return view('user.usernewdataset',['recordtypes' => $recordtypes]);
  }

  public function createNewDataset(Request $request)
  {
    $user = auth()->user();
    //ensure the required fields are present
    $datasetname = $request->dsn;
    $description = $request->description;
    $tags = explode(",,;", $request->tags);

    if (!$datasetname || !$description || !$tags) return redirect('myprofile/mydatasets');

    //Check temporalfrom and temporalto is valid, continue if it is or reject if it is not (do this in editDataset too)
    $temporalfrom = $request->temporalfrom;
    if (isset($temporalfrom)) {
      $temporalfrom = GeneralFunctions::dateMatchesRegexAndConvertString($temporalfrom);
      if (!$temporalfrom) return redirect('myprofile/mydatasets'); //The user bypassed the frontend js date check and submitted an incorrect date anyways, send them back to the datasets page
    }

    $temporalto = $request->temporalto;
    if (isset($temporalto)) {
      $temporalto = GeneralFunctions::dateMatchesRegexAndConvertString($temporalto);
      if (!$temporalto) return redirect('myprofile/mydatasets'); //The user bypassed the frontend js date check and submitted an incorrect date anyways, send them back to the datasets page
    }
  
    $keywords = [];
    //for each tag in the subjects array(?), get or create a new subjectkeyword
    foreach($tags as $tag) {
      $subjectkeyword = SubjectKeyword::firstOrCreate(['keyword' => strtolower( $tag )]);
      array_push($keywords, $subjectkeyword);
    }
    
    $recordtype_id = RecordType::where('type', $request->recordtype)->first()->id;

    $dataset = Dataset::create([
      'name' => $datasetname,
      'description' => $description,
      'recordtype_id' => $recordtype_id,
      'creator' => $request->creator,
      'public' => $request->public,
      'allowanps' => $request->allowanps,
      'publisher' => $request->publisher,
      'contact' => $request->contact,
      'citation' => $request->citation,
      'doi' => $request->doi,
      'source_url' => $request->source_url,
      'latitude_from' => $request->latitudefrom,
      'longitude_from' => $request->longitudefrom,
      'latitude_to' => $request->latitudeto,
      'longitude_to' => $request->longitudeto,
      'language' => $request->language,
      'license' => $request->license,
      'rights' => $request->rights,
      'temporal_from' => $temporalfrom,
      'temporal_to' => $temporalto,
      'created' => $request->created,
      'warning' => $request->warning,
    ]); 

    $user->datasets()->attach($dataset, ['dsrole' => 'OWNER']); //attach creator to pivot table as OWNER

    foreach($keywords as $keyword) {
      $dataset->subjectkeywords()->attach(['subject_keyword_id' => $keyword->id]);
    }
  
    return redirect('myprofile/mydatasets/' . $dataset->id);
  }

  public function userEditDataset(Request $request, int $id) {
    $user = auth()->user();
    $dataset = $user->datasets()->find($id);

    if (!$dataset) return redirect('myprofile/mydatasets'); //couldn't find dataset

    //Mandatory Fields
    $datasetname = $request->dsn;
    $description = $request->description;
    $tags = explode(",,;", $request->tags);

    if (!$datasetname || !$description || !$tags) return redirect('myprofile/mydatasets'); //Missing required fields
    
    //Check temporalfrom and temporalto is valid, continue if it is or reject if it is not
    $temporalfrom = $request->temporalfrom;
    if (isset($temporalfrom)) {
      $temporalfrom = GeneralFunctions::dateMatchesRegexAndConvertString($temporalfrom);
      if (!$temporalfrom) return redirect('myprofile/mydatasets'); //The user bypassed the frontend js date check and submitted an incorrect date anyways, send them back to the datasets page
    }

    $temporalto = $request->temporalto;
    if (isset($temporalto)) {
      $temporalto = GeneralFunctions::dateMatchesRegexAndConvertString($temporalto);
      if (!$temporalto) return redirect('myprofile/mydatasets'); //The user bypassed the frontend js date check and submitted an incorrect date anyways, send them back to the datasets page
    }
  
    $keywords = [];
    //for each tag in the subjects array(?), get or create a new subjectkeyword
    foreach($tags as $tag) {
      $subjectkeyword = SubjectKeyword::firstOrCreate(['keyword' => strtolower( $tag )]);
      array_push($keywords, $subjectkeyword);
    }

    $recordtype_id = RecordType::where('type', $request->recordtype)->first()->id;

    $dataset->fill([
      'name' => $datasetname,
      'description' => $description,
      'recordtype_id' => $recordtype_id,
      'creator' => $request->creator,
      'public' => $request->public,
      'allowanps' => $request->allowanps,
      'publisher' => $request->publisher,
      'contact' => $request->contact,
      'citation' => $request->citation,
      'doi' => $request->doi,
      'source_url' => $request->source_url,
      'latitude_from' => $request->latitudefrom,
      'longitude_from' => $request->longitudefrom,
      'latitude_to' => $request->latitudeto,
      'longitude_to' => $request->longitudeto,
      'language' => $request->language,
      'license' => $request->license,
      'rights' => $request->rights,
      'temporal_from' => $temporalfrom,
      'temporal_to' => $temporalto,
      'created' => $request->created,
      'warning' => $request->warning,
    ]);

    $dataset->save();

    $dataset->subjectkeywords()->detach(); //detach all keywords

    //Attach the new ones
    foreach($keywords as $keyword) {
      $dataset->subjectkeywords()->attach(['subject_keyword_id' => $keyword->id]);
    }

    return redirect('myprofile/mydatasets/' . $id);
  }

  /*
    data item add/edit/delete in AJAX CONTROLLER
  */

  /*
      Add to the dataset from file - can be .csv, .kml, or .json
      Will return to dataset with error message if incorrect file extension or if incorrectly formatted data
  */
  public function bulkAddDataitem(Request $request) {
    ini_set('upload_max_filesize', '10M');
    ini_set('post_max_size','10M');

    $this->middleware('auth'); //Throw error if not logged in?
    $user = auth()->user(); //currently logged in user
    $ds_id = $request->ds_id;
    $dataset = $user->datasets()->find($ds_id);

    if (!$dataset || ($dataset->pivot->dsrole != 'OWNER' && $dataset->pivot->dsrole != 'ADMIN' && $dataset->pivot->dsrole != 'COLLABORATOR')) 
            return redirect('myprofile/mydatasets'); //if dataset not found for this user or not ADMIN/COLLABORATOR, go back

    //get file
    $file = $request->fileToUpload;

    //overwrite style?
    $appendStyle = $request->appendStyle;
    $overwriteJourney = $request->overwriteJourney;

    //get file extension
    $ext = $file->getClientOriginalExtension();

    //get the fillable fields for dataitems
    $fillable = (new Dataitem())->getFillable();

    //Attempt a file read and parse
    try {
      if (strcasecmp($ext, 'csv') == 0) {
	      //parse cs

	      $arr = $this->csvToArray($file);

        if (!is_array($arr)) return redirect('myprofile/mydatasets/'.$ds_id)->with('error', 'Invalid date format in file on line ' . $arr); //if $arr is a number instead of an array, date format error where $arr is the offending line number

        $this->createDataitems($arr,$ds_id);
        
      }
      else if (strcasecmp($ext, 'kml') == 0) { //now handles extended data, journey
        $arr = $this->kmlToArray($file, $appendStyle);

        if (!is_array($arr)) return redirect('myprofile/mydatasets/'.$ds_id)->with('error', 'Invalid date format in file in node starting line ' . $arr);

        //If style/journey data found in KML && checkbox to overwrite was ticked - UPDATE THE DATASET
        if (array_key_exists('raw_journey', $arr)) {
          if ($overwriteJourney=="on") $dataset->update(['kml_journey' => $arr['raw_journey']]);
          unset($arr['raw_journey']); //remove the raw_journey from the end of the array 
        }
        if (array_key_exists('raw_style', $arr)) {
          if ($appendStyle=="on") $dataset->update(['kml_style' => $dataset['kml_style'] . $arr['raw_style']]); //APPEND not overwrite
          unset($arr['raw_style']); //remove the raw_style from the end of the array
        }
 
        //Call the function to create all the new data items from this array
        $this->createDataitems($arr,$ds_id);

      }
      else if (strcasecmp($ext, 'json') == 0) {
        //TODO extendeddata
        $arr = $this->geoJSONToArray($file);
        if (!is_array($arr)) return redirect('myprofile/mydatasets/'.$ds_id)->with('error', 'Invalid date format in file on line ' . $arr);

        $this->createDataitems($arr,$ds_id);
      }
      else {
        return redirect('myprofile/mydatasets/'.$ds_id)->with('error', 'Invalid file format for bulk add!'); //not a valid format, reload page with error msg
      }
    } catch (\Exception $e) {
      Log::error("IMPORT ERROR.");
      $extrainfo = "";
      if (isset($file)){
        $extrainfo = $extrainfo . " " . $file->getClientOriginalName();
      }
      if (isset($arr)){
        $extrainfo = $extrainfo . " Error on line " .json_encode($arr);
      }
      LOG::error("Import error. " . date(DATE_ATOM, mktime(0, 0, 0, 7, 1, 2000)) . " " . $e->getMessage() . " extra info " . $extrainfo);
      return redirect('myprofile/mydatasets/'.$ds_id)
        ->with('error', 'Error processing file. Please check it is in the right format and is less than 10Mb. If CSV, it must have
        a title or placename column. Check that lat, long and any dates are correct. ' . 
        date(DATE_ATOM, mktime(0, 0, 0, 7, 1, 2000)) . " " . $extrainfo)
        ->with('exception', $e->getMessage()); //file parsing threw an exception, reload page with error msg
    }  //catch any exception

    //update the dataset updated time
    $dataset->updated_at = Carbon::now();
    $dataset->save();

    return redirect('myprofile/mydatasets/'.$ds_id)->with('success', 'Successfully imported from file!'); //reload the page

  }

  function userEditCollaborators(Request $request, int $id) {
    $user = auth()->user(); //check authorization
    $dataset = $user->datasets()->find($id); //find dataset for this user
    if (!$dataset || ($dataset->pivot->dsrole != 'ADMIN' && $dataset->pivot->dsrole != 'OWNER')) return redirect('myprofile/mydatasets'); //if DS id doesnt exist OR user not ADMIN, return to DS page
    return view('user.usereditcollaborators',['ds' => $dataset,'user' => auth()->user()]);
  }
 
  /*
    Will create dataitems from the given array - will ignore column names that are not present in Dataitem

    $arr takes an array where each entry represents a dataitem of the form (['ds_id' => thedatasetid, 'placename' => someplacename, 'latitude' => 123, => etc...])
    $ds_id is the id for the dataset to add this data item into
   */
  function createDataitems($arr, $ds_id) { 
    $fillable = (new Dataitem)->getFillable(); //array of all the columns in the dataitem table that are fillable

    // detect names of fields that contain the dates and lat long

  $datecols = $this->aliasColPair($this->dateheadings,$arr);
	$llcols = $this->aliasColPair($this->llheadings, $arr);

	$notForExtData = ["id", "title", "placename", "name", "description", "type", "linkback"]; // because of special handling, as with date and lat long cols
        
	$extDataExclusions = array_merge($fillable,$datecols,$llcols,$notForExtData);



    for($i = 0; $i < count($arr); $i++) { //FOREACH data item
      $culled_array = array(); //we will cull out all keys that are not present as fillable fields
      $extendeddata = array(); //and add anything else to extended data.

      //More elegant, automated solution - as long as the kml has the correct column names
      foreach($arr[$i] as $key => &$value) {
        /* TODO: 
            This preg_replace cuts out all characters from <Data> names other than letters and underscores so we don't fail comparisons due to invisible chars... 
            - works for that but ignores many valid names! find a better solution 
        */
        $key = $this->sanitiseKey($key);
        $value = $this->sanitiseValue($value);
        //$key = preg_replace('/[^a-zA-Z_ ]/', '', $key); //REMOVE ALL NON ALPHA CHARACTERS FROM KEY NAME - some unseen chars were affecting string comparison ('placename' == 'placename' equating to false)
        /**
         * HERE IS THE SECTION FOR ADDING DATABASE ALIASES FOR USER UPLOADS OR FOR MANIPULATING DATA BEFORE ENTRY
         *    eg the database column is "placename" but we also want to accept "title" into this column
         *    Do this with the following line:
         *        else if ($key == "title") { $culled_array["placename"] = $value; }
         * 
	 *    This will be overwritten if "placemark" is also present
	 *
	 *    ! Bill Pascoe: this is being changed as the policy for user layers is to have a col for both title and placename, since the title might not be a placename
	 *    and there must always be a title but placename is optional. If there is a placename and no title, the title defaults to placename.
	 *    none the less, user may upload file with 'title' or 'placename' as the column, or both. So we have to handle that.
         */
// we are looping each column and checking it's name looking to handle the crucial ones...
        //if array has the "type" key, change it to "recordtype_id" and change all of the values to the actual id for the given type, or "Other" if it does not match
        if ($key == "type") {
          $recordtype = RecordType::where("type", trim($value))->first(); //get the this recordtype from "type" name
          $culled_array["recordtype_id"] = ($recordtype) ? $recordtype->id : 1; //if recordtype does exist, set the recordtype_id to its id, otherwise set it to 1 (default "Other")
	      }
	// formerly we only had placename field and populated it with 'title', but now we have both, see below.
        //else if ($key == "title") { $culled_array["placename"] = $value; } //if we upload a file with "title" key, set it to be the placemark
	//} 
        else if ($key == "linkback") { $culled_array["external_url"] = $value; } 
        //For all other keys (except id) push the key value combo into the culled array
        else if (in_array($key,$fillable) && $key != 'id') { $culled_array[$key] = $value;} //if the key is present as a fillable field for dataitems, then we keep it - DO NOT PUSH ID< THIS IS GENERATED AUTOMATICALLY
	 else if (!in_array($key, $extDataExclusions)) {

		array_push($extendeddata, $key); // all the extra cols that will go in 'extended data' kml chunk.
        }
      }
    
    // BP: set title to placename if title is empty.  
    //
    // Now having looked at some aliases for column names, we can look at being forgiving and handling various cases of columns 
    // that have common names, and also the whole placename issue.
    //
    //Handle title and placename
      if ((!isset($culled_array["title"])) && (isset($arr[$i]["placename"]))) {
	$culled_array["title"] = $arr[$i]["placename"];
      }
      if ((!isset($culled_array["title"])) && (isset($arr[$i]["name"]))) {
              $culled_array["title"] = $arr[$i]["name"];
      }
      if (!isset($culled_array["title"])){
	      throw new \Exception('Could not find a title, placename or name column to use as Title.');
      }
      if ($culled_array["title"] === NULL){
              throw new \Exception('Title, placename or name column to use as Title is null or empty.');
      }

    // Handle possible names for date columns

if (!isset($culled_array["datestart"]) && !empty($datecols)) {
	$culled_array["datestart"] = $arr[$i][$datecols[0]];
	$culled_array["dateend"] = $arr[$i][$datecols[1]];
}
if (!isset($culled_array["longitude"]) && !empty($llcols)) {
        $culled_array["latitude"] = $arr[$i][$llcols[0]];
        $culled_array["longitude"] = $arr[$i][$llcols[1]];
}
      
if (!empty($extendeddata)){
	$extdata = '<ExtendedData>';
  
	foreach ($extendeddata as $ed) {
	    $extdata = $extdata . 
	    '<Data name="' . $ed . '">
                <value><![CDATA[' . $arr[$i][$ed] . ']]></value>
            </Data>';
	}
	$extdata = $extdata . '</ExtendedData>';
	$culled_array["extended_data"] = $extdata;
}      

      if (!empty($culled_array)) { //ignore empties
        
        $result = Dataitem::firstOrCreate(array_merge(array('dataset_id' => $ds_id), $culled_array)); //Insert into DB - THIS WILL IGNORE EXACT DUPLICATES WITHIN THIS DATASET
        
        
      }

    }
  }
// trawl for lat long col names


// detect commonly used column or attribute names that come in pairs, such as 'begin' and 'end' or 'lat' and 'lng'.
// Pass in array of arrays of possible headings for date and latlong columns, and the key value array of headings from the spreadsheet. 
// Returns the 2 element array containing the matched keys/column headings, or empty array. 

// This looks like it should be something simple. It may be that it could be made simple, but here's why it's complicated at the moment.
// We can't just check if any of the headings match our list of possible date or lat lng keywords, because check if this heading isset in this other array is case sensitive.
// we don't want to put every possible case combination in our list of key words to check so we want case insenstive matching.
// You would think you could just loop through the first record, not the whole lot. TBH maybe you can and I haven't checked it properly, 
// But one reason that maybe you can't is because null or empty values were left out of the key values pairs, so you have to loop the entire dataset, to see if there is a 
// named date column for just a few records, while most were null or empty.
function aliasColPair($cols, $arr) {

    $checkhead = $arr;
    $headkeysfound = ['',''];
    //need to do case insenstive matching, so...
    for($i = 0; $i < count($arr); $i++) {

      foreach (array_keys($arr[$i]) as $colkey) {
        
        foreach ($cols as $c) {
         
          if (strtolower($colkey) === strtolower($c[0])) {
            
            $headkeysfound[0] = $colkey;
          }
          if (strtolower($colkey) === strtolower($c[1])) {

            $headkeysfound[1] = $colkey;
          }
        }
        
        if (!empty($headkeysfound[0]) && !empty($headkeysfound[1])) {
          return $headkeysfound;
        }
      }
      //$checkhead[$i] = strtolower($arr[$i]);
    }
/*
    for($i = 0; $i < count($checkhead); $i++) {
      foreach ($cols as $c) {
        if ((isset($checkhead[$i][strtolower($c[0])])) && (isset($checkhead[strtolower($i)][strtolower($c[1])]))) {
        //if ((isset($arr[$i][$c[0]])) && (isset($arr[$i][$c[1]]))) {
          return $c;
        }
      }
    }
    */
    return array();

}




function matchLL($arr) {
    $llcols = [
	["latitude","longitude"],
	["lat","long"],
	["lat","lng"]
];
    for($i = 0; $i < count($arr); $i++) {
      foreach ($llcols as $llc) {
        if ((isset($arr[$i][$llc[0]])) && (isset($arr[$i][$llc[1]]))) {
          return $llc;
        }
      }
    }
    return array();

}


// trawl the data for possible date columns
  function matchDates($arr) {
    // note that col headings in csv already have no whitespace and converted to lower case
    $datecols = [
      ["datestart","dateend"],
      ["date start","date end"],
      ["begin","end"],
      ["startdate","enddate"],
      ["start date","end date"],
      ["date","date"] // if there is a single date set begin and end to same
    ];
    
    for($i = 0; $i < count($arr); $i++) {
      foreach ($datecols as $dc) {
        if ((isset($arr[$i][$dc[0]])) && (isset($arr[$i][$dc[1]]))) {
          return $dc;
        }
      }
    }
    return array();
  }
  /*
    convert csv file to array
    https://stackoverflow.com/questions/35220048/import-csv-file-to-laravel-controller-and-insert-data-to-two-tables
//$lines = str_getcsv($file->get(),"\n");
    Note: Each line in the csv must contain a value for all entries in the header
  */
// !!!!!! this is failing to handle line breaks in cells. Need to handle that.
    //$lines = fgetcsv($file->get(), "\n");//Split entire file into array of lines on \n    OLD: explode(PHP_EOL,$file->get());
    // Try fgetcsv instead.

  // Refactoring this entirely, as the old way didn't handle multiline cells. Output should be the same.
  // The purpose of this section is to get the CSV, sanitise the headers, and handle date formatting. 
  // Note that handling presence of columns by different names such as lat, lng, linkback, title etc is done elsewhere.
  // (Feels like it could / should be done in the same process. But just need to get it working so not digressing... 
  // and it might make sense after all since this is just for CSV but later we can handle any input after ingest)
  // and reformat like this: 
  //data[this] is now an array mapping header to field eg data[this] = ['placename' => 'newcastle', ... => ..., etc]
        
  function csvToArray($file, $delimiter = ',') {
    
    $header = null;
    $data = array();
    $datestartindex = false; 
    $dateendindex = false;


    try{

if(($handle = fopen($file->getRealPath(), 'r')) !== FALSE) {
  // necessary if a large csv file
  set_time_limit(0);
  $row = 0;
  
  while(($data = fgetcsv($handle)) !== FALSE) {
      // number of fields in the csv
      $col_count = count($data);
      // sanitise headings
      if ($row === 0) {
        
        // sanitise and check for required fields
        $header = $data;
        foreach ($header as &$heading) {
          $heading = $this->sanitiseKey($heading);

        }
        
        
         //if the uploaded data includes datestart or dateend values, store the index
         // dates might be in datestart or date end, or there may be a single date field.
        $datestartindex = array_search('datestart', $header); //if the uploaded header includes datestart or dateend values, store the index
	      $dateendindex = array_search('dateend', $header);


	      if ($datestartindex === false) {
          
		      $datestartindex = array_search('startdate', $header);
          
	      }
        if ($datestartindex === false) {
		      $datestartindex = array_search('start date', $header);
	      }
	      if ($datestartindex === false) {
		      $datestartindex = array_search('begin', $header);
	      }
	      if ($dateendindex === false) {
		      $dateendindex = array_search('enddate', $header);
	      }
        if ($dateendindex === false) {
		      $dateendindex = array_search('end date', $header);
	      }
	      if ($dateendindex === false) {
	 	      $dateendindex = array_search('end', $header);
	      }
        
	      // this part is just for checking the date formatting. We'll handle default and mapping fields back in the calling function.
	      if (($datestartindex === false) && ($dateendindex === false)) {
		      $datestartindex = array_search('date', $header);
          $dateendindex = array_search('date', $header);
	      }

      } else { // not a heading format the dates
        $fields = $data;
        
        if ($datestartindex !== false) {

          $parsedate = GeneralFunctions::dateMatchesRegexAndConvertString($fields[$datestartindex]);
          if ($parsedate === -1) {
            return $row;
          } else {
            $fields[$datestartindex] = $parsedate;
          }
          //if (!($fields[$datestartindex] = GeneralFunctions::dateMatchesRegexAndConvertString($fields[$datestartindex]))) return $row; //if datestart or dateend exist, check the values on this line 
        }
        if ($dateendindex !== false) {
          $parsedate = GeneralFunctions::dateMatchesRegexAndConvertString($fields[$dateendindex]);
          if ($parsedate === -1) {
            return $row;
          } else {
            $fields[$dateendindex] = $parsedate;
          }
          //if (!($fields[$dateendindex] = GeneralFunctions::dateMatchesRegexAndConvertString($fields[$dateendindex]))) return $row;
        }

        $outdata[] = array_combine($header,$fields); //data[this] is now an array mapping header to field eg data[this] = ['placename' => 'newcastle', ... => ..., etc]
        
      }
      

      $row++;
  }
  fclose($handle);
} else {
  LOG::error( "File import error NO HANDLE");
  throw new Exception('File import error NO HANDLE');
}



  } catch (Exception $e) {
    LOG::error( 'File Import Caught exception: ',  $e->getMessage(), "\n");
  }


    return $outdata; //return the array of lines
  }

  function sanitiseKey($s) {
    // remove spaces and dodgy characters
    $s = trim($s);
    $s = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $s);
    $s = preg_replace('/[^a-zA-Z_ ]/', '', $s);
    $s = iconv("UTF-8","UTF-8//IGNORE",$s);
    // this lc handling seems a bit clumsy, but we want to convert these main keys to lc for easy identification without having to 
    // convert in a lot of clumsy comparison elsewhere, yet we can't just lc everything, cause we can't assume the case when outputting,
    // so need to retain case for other things like extended data. Noticed glitch between lcing everying in CSV, but not in KML, so was
    // no way out but this.
    $notForExtData = ["id", "title", "placename", "name", "description", "type", "linkback", "latitude", "longitude",
      "startdate","enddate","date","datestart","dateend","begin","end","linkback","external_url"];
    if (in_array(strtolower($s), array_map('strtolower', $notForExtData))) {
      $s = strtolower($s);
    }
    return $s;
  }
  function sanitiseValue($s) {
    $s = iconv("UTF-8","UTF-8//IGNORE",$s);
    return $s;
  }
  //array of asoc arrays of form  [['placename' => 'newcastle', 'latitude' => 123.456, etc], ['placename' => etc], ['placename' => etc]]
  //sppendStyle is true if we want to grab the styleUrl tag for each placemark (if it exists) and import that to the database as well
  function kmlToArray($file, $appendStyle=false) {
    //dd($this->getArea([[-10,-10],[-20,-10],[-20,-20],[-10,-20]])); //expected 100
    //dd($this->getCentroid([[-10,-10],[-20,-10],[-20,-20],[-10,-20]])); //expected -15,-15
    //dd($this->getMidpoint([[0,0], [0,5], [3,10]])); //expected 1,5

    $data = array();
    $xml_object = simplexml_load_file($file, null, LIBXML_NOERROR);
    $raw_journey = null;
    $raw_style = null;

    //compound all the style tags into one var
    if (!empty($xml_object->Document->Style) ) {
      foreach($xml_object->Document->Style as $style) {  $raw_style .= $style->asXml(); } 
    }
    if (!empty($xml_object->Document->StyleMap) ) {
      foreach($xml_object->Document->StyleMap as $style) {  $raw_style .= $style->asXml(); } 
    }

    //Get each placemark as an associative array, and put that into an array - include journey/style data on the end
    foreach( $xml_object->xpath("//*[local-name()='Placemark']") as $place) { //Get all Placemark objects regardless of where they are in the tree, and regardless of the kml namespace
      if (!empty($place->children('gx',TRUE)->Track)) { //if place is actually JOURNEY DATA
        $raw_journey = $place->children('gx',TRUE)->Track->asXml(); //set the var to the raw xml of the journey segment
      }
      else { //else it is a point, line, polygon, etc
        $curr = $this->placemarkToData($place, $appendStyle);
        if (!is_array($curr)) return $curr; //if $curr is a number instead of an array we had a date format error where $curr is the offending line number
        $data[] = $curr;
      }
    }

    //append raw journey and style info
    if ($raw_journey) $data = $data + array("raw_journey" => $raw_journey);
    if ($raw_style) $data = $data + array("raw_style" => $raw_style);
    return $data;
  }
  
  //Placemarks can be Points Polygons or LineStrings - we just need to work out the point we want to use and set it as coordinates[] (long, lat)
  function placemarkToData($place, $appendStyle=false){
      $ed_out = array(); //reset the ed_out var
      $ed_raw = null; //holds raw content of extended data (including <ExtendedData> tag)
      
      if (!empty($place->Point)) {
        $coordinates = explode(',',$place->Point->coordinates); //coordinates of form <coordinates>LONG,LAT,ALT</coordinates>, split around commas
      }
      else if (!empty($place->Polygon)) {
        $trimmed = trim(str_replace(" ", "", $place->Polygon->outerBoundaryIs->LinearRing->coordinates));
        $points = explode("\n",$trimmed);
        for($i=0; $i<count($points); $i++) {
          $points[$i] = explode(",",$points[$i]);
        }
        $coordinates = $this->getCentroid($points);
      }
      else if (!empty($place->LineString)) {
        $trimmed = trim(str_replace(" ", "", $place->LineString->coordinates));
        $points = explode("\n",$trimmed);
        for($i=0; $i<count($points); $i++) {
          $points[$i] = explode(",",$points[$i]);
        }
        $coordinates = $this->getMidpoint($points);
      }
      else { return array(); }
		  
      $description = (!empty($place->description)) ? $place->description : "";

      $datestart = null;
      $dateend = null;
      $kml_style_url = null;

      if (!empty($place->TimeSpan)) { //Get Timespan values 
        if (!empty($place->TimeSpan->begin)) { //if we have a begin node
          $datestart = $place->TimeSpan->begin;  //set $datestart value
          if (!($datestart = GeneralFunctions::dateMatchesRegexAndConvertString($datestart))) {
            $node = dom_import_simplexml($place->TimeSpan->begin); //convert simplexml to DOMElement
            return $node->getLineNo(); //get line number of the offending line
          } //else it was validly formatted
        }
        if (!empty($place->TimeSpan->end)) { //if we have an end node
          $dateend = $place->TimeSpan->end;  //set $dateend value
          if (!($dateend = GeneralFunctions::dateMatchesRegexAndConvertString($dateend))) {
            $node = dom_import_simplexml($place->TimeSpan->end);
            return $node->getLineNo();
          } //else it was validly formatted
        }
      }

      if ($appendStyle && !empty($place->styleUrl)) {
        $kml_style_url = $place->styleUrl->asXml();
      }

      
      //Grab all of the extended data - we cull irrelevant fields in the createDataItems function
		  if (!empty($place->ExtendedData)) {
        $ed_raw = $place->ExtendedData->asXml();
			  foreach($place->ExtendedData->Data as $ed) { //for each entry in extended data
				$ed_out[strval($ed['name'])] = strval($ed->value); //such as <Data name = "description">, will pull 'description' out as the asoc array key
			  }
      }
      
      return array("title" 
        => strval($place->name), "description" => $description, "longitude" => $coordinates[0], "latitude" => $coordinates[1], "datestart" => $datestart, "dateend" => $dateend, "kml_style_url" => $kml_style_url)  
          + $ed_out + array("extended_data" => $ed_raw); //adding on the extended data
  }

  //Return a coordinate array [longitude,latitude] representing the center of a polygon
  function getCentroid($points) {
    if ($points[0] != $points[count($points)-1]) array_push($points, $points[0]); //if final point does not equal first point, add first point as final point
    $n = count($points);
    $A = $this->getArea($points);
    $Cx = $Cy = 0;
    for ($i=0; $i<$n-1; $i++) {
      $suf = ((float)$points[$i][0] * (float)$points[$i+1][1] - (float)$points[$i+1][0] * (float)$points[$i][1]); //end portion of the formula is the same for Cx and Cy on each iteration, so just calculate it once
      $Cx += ((float)$points[$i][0] + (float)$points[$i+1][0]) * $suf;
      $Cy += ((float)$points[$i][1] + (float)$points[$i+1][1]) * $suf;
    }
    $Cx *= (1/(6*$A));
    $Cy *= (1/(6*$A));
    return [$Cx, $Cy]; //long, lat
  }

  //Return the area of a Polygon from its poins
  function getArea($points) {
    if ($points[0] != $points[count($points)-1]) array_push($points, $points[0]); //if final point does not equal first point, add first point as final point
    $n = count($points);
    Log::debug($points);
    Log::debug($n);
    $sum = 0;
    for ($i=0; $i<$n-1; $i++) {
      $sum += ( ((float)$points[$i][0] * (float)$points[$i+1][1]) - ((float)$points[$i+1][0] * (float)$points[$i][1]) ); //sum from 0 to n-1 (inclusive) of xi*yi+1 - xi+1*yi
    }
    return abs($sum/2);
  }

  //Return a coordinate array [longitude,latitude] representing the midpoint of a line segment, or multiline segment (as the avg of points)
  function getMidpoint($points) {
    $n = count($points);
    $x = $y = 0;
    for ($i=0; $i<$n; $i++) {
      $x += (float)$points[$i][0]; //sum of all x
      $y += (float)$points[$i][1]; //sum of all y
    }
    return [$x/$n, $y/$n]; //divide by n to get the average
  }


  function geoJSONToArray($file) {
    $data = array();
    $geojson = json_decode($file->get());
    $features = $geojson->features;
    foreach($features as $feature) {
      $ed_out = array(); //reset the ed_out var
      foreach($feature as $key => $value) { //for each data entry for this place
        if ($key != "type" && $key != "geometry" && $key != "properties") 
                      $ed_out[strval($key)] = strval($value); //ignoring 'type' 'geometry' and 'properties', add each key val pair to $ed_out
      }
      $data[] = array("title" => $feature->properties->name, "longitude" => $feature->geometry->coordinates[0], 
                      "latitude" => $feature->geometry->coordinates[1]) + $ed_out; //adding on the extended data
    }
    return $data;
  }

}
