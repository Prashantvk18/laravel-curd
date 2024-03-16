<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Trip;
use App\Models\Trip_member;
use App\Models\Expanse;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Validator;

class managePriceController extends Controller
{
    //
    public function manage_expanse() {
        $user = \Auth::User();
        $trip_member = Trip_member::where('member_mobile',$user->mobile_no)->get();
        $trip_id = $trip_member->pluck('trip_id')->first();
        $permission_arr[] = '';
        foreach($trip_member as $t_mem){
           $permission_arr[$trip_id] = $t_mem->can_edit;
        }
        $value2 = $user->id;

        // $trip = Trip::where('is_deleted',0)
        // ->Where(function ($query) use ($value2, $trip_id) {
        //     $query->where('created_by','=',$value2)
        //           ->orwhere('id','=',$trip_id);
        // })->get();


        $trip = DB::table('trips')
            ->join('users', 'trips.created_by', '=', 'users.id')
            ->select('trips.*', 'users.name as username')
            ->where('trips.is_deleted',0)
            ->Where(function ($query) use ($value2, $trip_id) {
                $query->where('trips.created_by','=',$value2)
                      ->orwhere('trips.id','=',$trip_id);
            })->get();           
        return view('ManageExpanse.ManageExpanse',['trips' => $trip , 'trip_member' => $permission_arr]);
    }

    public function add_trip(Request $request) {
        
        $trip = Trip::where('id',$request->id)->first();
        
        return response()->json(['data' => view('ManageExpanse.addTrip',['trips'=>$trip,'id'=>$request->id])->render()]);
    }

    public function save_trip(Request $request)
    {
        
        // $request->validate([
        //     'trip_Name' => 'required',
        //     'trip_location'  => 'required',
        //     'trip_to_date'=> 'required',
        //     'trip_from_date'=> 'required'
        // ]);

        $validator = Validator::make($request->all(), [
            'trip_Name' => 'required',
            'trip_location'  => 'required',
            'trip_to_date'=> 'required',
            'trip_from_date'=> 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        try {
            $user = \Auth::user();
            
            if($request->id>0){
                $trips = Trip::where('id',$request->id)->first();
                $trips->updated_by = $user->id;
            }else{
                $trips = new Trip();
                $trips->created_by = $user->id;
            }
            
            $trips->Trip_Name = $request->trip_Name;
            $trips->Trip_location = $request->trip_location;
            $trips->trip_fdate = $request->trip_from_date;
            $trips->trip_tdate = $request->trip_to_date;
            
            $trips->save();
            return response()->json(['message' => 'Trip Added Successfully', 'redirect' => '/manage_expanse']);
        }
        catch (\Exception $e) {
            //Log::error('An unexpected error occurred: ' . $e->getMessage());
            return view('ManageExpanse.addTrip');
        }
    }

    public function delete_trip (Request $request) {
        $trip_soft_delete = Trip::where('id',$request->id)->update(['is_deleted' => 1]);
        return response()->json(['msg' => 'Trip deleted successfully' , 'status' => 'success']);
    }

    //--------------------------Add Members --------------------------------------//
    
    public function add_members () {
        $id = $_GET['id'];
        $trip = Trip::where('id',$id)->first(); 
        $trip_data = [];
        $trip_data['trip'] = $trip;
        if(isset($_GET['mid'])){
            $trip_member = Trip_member::where('id',$_GET['mid'])->first();
            $trip_data['trip_member'] = $trip_member;
        }   
        return response()->json(['data' => view('ManageExpanse.addMembers',$trip_data)->render()]);
    }

    public function save_members (Request $request) {
        
        $user = \Auth::user();
        if($request->mid > 0){
            $trip_member =  Trip_member::where('id',$request->mid)->first();
            $msg = 'Memeber Updated successfulluy';
            $status= 'success'; //->where('member_mobile',$request->mem_number)
            if($trip_member->member_mobile != $request->mem_number){
                $ismember_present =  Trip_member::where('member_mobile',$request->mem_number)->where('id' , '<>' , $request->mid)->where('trip_id',$request->trip_id)->get();
                if(count($ismember_present) >= 1){
                    $ms1g = 'Member is already added for this trip';
                    $status = 'error';
                    return response()->json(['msg' => $msg , 'status' => $status]);
                }
            }
           
        }else{
            $ismember_present1 =  Trip_member::where('member_mobile',$request->mem_number)->where('trip_id',$request->trip_id)->get();
            
            if(count($ismember_present) >= 1){
                $msg = 'Member is already added for this trip';
                $status = 'error';
                return response()->json(['msg' => $msg , 'status' => $status]);
            }
            $trip_member = new Trip_member();
            $trip_member->trip_id = $request->trip_id;
            $trip_member->added_by = $user->id;
            $msg = 'Member added successfulluy';
            $status= 'success';
        }
        $trip_member->total_expense = $request->paid;
        $trip_member->member_name = $request->mem_name;
        $trip_member->member_mobile = $request->mem_number;
        $trip_member->can_edit = $request->can_edit;
        $trip_member->save();
        return response()->json(['msg' => $msg , 'status' => $status]);
    }

    public function view_members () {
        $id = $_GET['id'];
        $can_view = $_GET['view'];
        $user = \Auth::User();
        $trip_member = Trip_member::where('trip_id',$id)->get();
        $created = Trip::where('id' , $id)->where('created_by',$user->id)->get();
        $can_edit  = 0;
        if(count($created) == 1){
            $can_edit =1;
        }
        $trip_member1 = Trip_member::where('member_mobile',$user->mobile_no)->first();
        if(count($trip_member) > 0){
            return response()->json(['status'=>'success' , 'data' => view('ManageExpanse.viewMembers',['trip_member' => $trip_member,'can_view'=>$trip_member1->can_edit , 'can_edit' => $can_edit])->render()]);
        }
        return response()->json(['status'=>'error' , 'msg' => 'There is no memeber to display !!!!']);
       
    }

    public function delete_member(Request $request) {
        $trip_member = Trip_member::where('id',$request->mid)->first();
        $trip_member->delete();
        return response()->json(['status'=>'success' , 'msg' => 'Member deleted successfully']);
    }
//---------------------------------------Expanse -------------------------------//

    public function trip_expanse(){
        //Below code user should create the trip than only it can access the expanse else only visible
        $id = $_GET['id'];
        $trip='';
        $user = \Auth::User();
        $trip = Trip::where('id',$id)->where('is_deleted' , 0)->where('created_by' , $user->id)->first();
        $expanse = Expanse::where('trip_id',$id)->get();
        if(!empty($trip)){
            // $trip = Trip::where('id',$request->id)->where('is_deleted' , 0)->first();
            $trip_member = Trip_member::where('trip_id' , $id)->get();
            return view('ManageExpanse.viewExpanse',['trip' => $trip , 'trip_member' => $trip_member,'access'=>1 ,'expanse' => $expanse ]);
            
        }
        //below code for use must be memeber of trip then only can access expanse
        if(empty($trip)){
            $trip_member = Trip_member::where('trip_id' , $id)->where('member_mobile',$user->mobile_no)->first();
            if(!empty($trip_member)){
                $trip = Trip::where('id',$id)->where('is_deleted' , 0)->first(); 
                return view('ManageExpanse.viewExpanse',['trip' => $trip , 'trip_member' => $trip_member,'expanse' => $expanse ]);
                
            }
        }
        
        return redirect(url('manage_expanse'));
        
    }

    public function add_expanse(Request $request){
        $id = $request->id;
        $expanse = '';
        if($request->pid > 0){
            $expanse = Expanse::where('id', $request->pid)->first();
        }
        $trip_member = Trip_member::where('trip_id',$id)->get();
        return response()->json(['data' => view('ManageExpanse.addExpanse',['trip_member' => $trip_member, 'trip_id' => $id , 'expanse' => $expanse])->render()]);
    }

    public function save_expanse(Request $request){
        $user = \Auth::User();
        if($request->eid > 0){
            $expanse  = Expanse::where('id', $request->eid)->first();
            $expanse->updated_by = $user->id;
        }else{
            $expanse  = new Expanse();
            $expanse->added_by = $user->id;
        }
        $selected_mem = implode(',',$request->check_mem);
        $expanse->trip_id = $request->id;
        $expanse->product_name = $request->pname;
        $expanse->price = $request->price;
        $expanse->divide_among = $selected_mem;
        
        $expanse->save();

        // $each_contro =$request->price /  count($request->check_mem);
        // $update_amnt = Trip_member::whereIn('id' , $request->check_mem)->update(['pending_amount' => $each_contro]);

        return response()->json(['status' => 'success','msg'=>'Product added successfully']);
        // $expanse->updated_by = $request->
    }
    public function delete_expanse(){
        $id = $_GET['id'];
        $trip_member = Expanse::where('id',$id)->first();
        $trip_member->delete();
        return response()->json(['status'=>'success' , 'msg' => 'Member deleted successfully']);
    }

    public function submit_expanse(){
        $trip_id = $_GET['id'];
        $array = [];
        $trip_expanse = Expanse::where('trip_id',$trip_id)->get();
        if(count($trip_expanse) > 0){
            $trip_mem = Trip_member::where('trip_id',$trip_id)->get();
            foreach($trip_mem as $data){
                $array[$data->id] = 0;
            }
            foreach($trip_expanse as $trip){
                $selected_mem_arr = explode(',',$trip->divide_among);
                $count = count($selected_mem_arr);
                $each_contro = $trip->price / $count;
                foreach($selected_mem_arr as $val){
                    $array[$val] =  $array[$val] + $each_contro;
                }
            }
            foreach($array as $key => $value){
                $update_amnt = Trip_member::where('id' , $key)->update(['pending_amount' => $value]);
                $update_exp = Expanse::where('id',$_GET['eid'])->update(['is_submit' => 1]);
                // $update_amnt = Trip_member::where('id' , $key)->get();
                // dd($update_amnt);
                // $update_amt->pending_amount =$datas;
                // $update_amt->update();
            }
        }
         
        return response()->json(['status'=>'success' , 'msg' => 'Member deleted successfully']);
    }
    
    

}
