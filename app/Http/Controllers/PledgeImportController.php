<?php

namespace App\Http\Controllers;

use App\Models\Wedding;
use App\Services\PledgeImporter;
use Illuminate\Http\Request;

class PledgeImportController extends Controller
{
    /** Show the upload form. */
    public function index(Wedding $wedding)
    {
        return view('pledges.import', [
            'wedding' => $wedding,
            'rows' => null,
            'headers' => null,
            'notes' => [],
        ]);
    }

    /**
     * Parse the upload and show what would happen, without writing anything.
     *
     * Importing money is easy to get wrong and painful to undo, so the user
     * always sees a parsed preview before any row reaches the database.
     */
    public function preview(Request $request, Wedding $wedding)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,tsv,xlsx', 'max:5120'],
        ], [
            'file.mimes' => 'Please upload a CSV or Excel (.xlsx) file. Older .xls files must be saved as .xlsx or CSV first.',
        ]);

        $importer = new PledgeImporter($wedding);
        $result = $importer->preview($request->file('file')->getRealPath());

        if ($result['rows'] === []) {
            return back()->withErrors([
                'file' => 'No usable rows found. Check the file has a header row with a "name" column.',
            ]);
        }

        // Stash the parsed rows so the confirm step does not need the upload
        // again. Session keeps this per-user and short-lived.
        $request->session()->put('pledge_import.rows', $result['rows']);
        $request->session()->put('pledge_import.wedding_id', $wedding->id);

        return view('pledges.import', [
            'wedding' => $wedding,
            'rows' => $result['rows'],
            'headers' => $result['headers'],
            'notes' => $result['errors'],
        ]);
    }

    /** Commit the previewed rows. */
    public function store(Request $request, Wedding $wedding)
    {
        $rows = $request->session()->get('pledge_import.rows');
        $weddingId = $request->session()->get('pledge_import.wedding_id');

        if (! is_array($rows) || $rows === []) {
            return redirect()
                ->route('pledges.import.index', $wedding)
                ->withErrors(['file' => 'That preview expired. Please upload the file again.']);
        }

        // Guard against a preview from one wedding being committed into
        // another after the user switches context in a second tab.
        if ((int) $weddingId !== $wedding->id) {
            $request->session()->forget(['pledge_import.rows', 'pledge_import.wedding_id']);

            return redirect()
                ->route('pledges.import.index', $wedding)
                ->withErrors(['file' => 'That preview belongs to a different wedding. Please upload the file again.']);
        }

        $stats = (new PledgeImporter($wedding))->import($rows);

        $request->session()->forget(['pledge_import.rows', 'pledge_import.wedding_id']);

        $message = sprintf(
            '%d pledge(s) added, %d updated, %d contribution(s) recorded.',
            $stats['created'],
            $stats['updated'],
            $stats['contributions']
        );

        if ($stats['skipped'] > 0) {
            $message .= sprintf(' %d row(s) skipped.', $stats['skipped']);
        }

        return redirect()->route('pledges.index', $wedding)->with('success', $message);
    }

    /** Download a template with the expected headers and one example row. */
    public function template(Wedding $wedding)
    {
        $csv = "name,pledge,email,phone,amount,date,contribution\n"
            .'"Asha Mwinyi","Cash gift","asha@example.com","+255712345678","500000","2026-08-01","200000"'."\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="pledge-import-template.csv"',
        ]);
    }
}
