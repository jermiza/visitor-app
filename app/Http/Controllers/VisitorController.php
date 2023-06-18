<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;


class VisitorController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth');
    // }

    public function index(Request $request)
    {
        $search_keyword = $request->query('search');
        $datein_from = $request->query('checkin_from');
        $datein_to = $request->query('checkin_to');

        $visitor_records = Visitor::where(function ($query) {
            $query->whereDate('datetime_in', '<=', date('Y-m-d'));
        });

        if (!empty($search_keyword)) {
            $visitor_records->where(function ($query) use ($search_keyword) {
                $query->where('name', 'LIKE', '%' . $search_keyword . '%')
                    ->orWhere('email', 'LIKE', '%' . $search_keyword . '%')
                    ->orWhere('purpose', 'LIKE', '%' . $search_keyword . '%')
                    ->orWhere('contact', 'LIKE', '%' . $search_keyword . '%')
                    ->orWhere('transport', 'LIKE', '%' . $search_keyword . '%');
            });
        }
        if (!empty($datein_from)) {
            $visitor_records->where('datetime_in', '>=', $datein_from . ' 00:00:00');
        }
        if (!empty($datein_to)) {
            $visitor_records->where('datetime_in', '<=', $datein_to . ' 23:59:59');
        }

        $visitor_records = $visitor_records->paginate(10);
        // dd($visitor_records);

        return view('visitor.index', ['records' => $visitor_records]);
    }

    public function show($id)
    {
        // $userid = auth()->user()->id;
        $record = Visitor::findOrFail($id);
        return view('visitor.show', ['record' => $record]);
    }


    public function create()
    {
        return view('visitor.create');
    }

    public function store(Request $request)
    {
        // dd($request->all());
        $this->validate($request, [
            'name' => 'required|min:3',
            'contact' => 'required|min:8|max:15',
            'email' => 'required|email',
            'purpose' => 'required|max:255',
            'transport' => 'required|max:10',
            // 'vehicle_no' => 'required_if:transport,vehicle'
        ], [
            '*.required' => 'This field is required',
            'name.min' => 'min 3 characters',
            'contact.min' => 'Min 8 digits',
            'contact.max' => 'Exceeded max 15 digits'
            // 'contact.required' => 'This field is required',
        ]);

        $filename = '';
        if ($request->hasFile('document')) {
            $filename = $request->getSchemeAndHttpHost(). '/docs/' . time() . '.' . $request->document->extension();
            // dd($filename);
            $request->document->move(public_path('/docs/'), $filename );
        }


        $current = Carbon::now();
        Visitor::create([
            'name' => htmlentities($request->name),
            'email' => $request->email,
            'contact' => htmlentities($request->contact),
            'purpose' => htmlentities($request->purpose),
            'datetime_in' => $current->toDateTimeString(),
            'transport' => $request->transport,
            'filepath' => $filename,
            // 'datetime_in' => date('Y-m-d H:i:s')
        ]);

        return redirect('/visitor/register')->with('success', 'You have registered successfully.');
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'id' => 'required|numeric|',
        ], [
            '*.required' => 'numberic input is required',
            '*.numeric' => 'numberic input is required',
        ]);
        $current = Carbon::now();
        $record = Visitor::findOrFail($id);
        $record->datetime_out = $current->toDateTimeString();
        $record->save();
        return redirect('/admin/visitor/' . $request->id)->with('success', 'You have check out successfully.');
    }

    public function viewPDF()
    {

        $visitors = Visitor::all();
        $pdf = PDF::loadView('pdf.visitorlist', array('visitors' => $visitors))->setPaper('a4', 'landscape');
        return $pdf->stream();
    }

    public function downloadPDF()
    {

        $visitors = Visitor::all();
        $pdf = PDF::loadView('pdf.visitorlist', array('visitors' => $visitors))->setPaper('a4', 'landscape');
        return $pdf->download('visitors-list.pdf');
    }
}
