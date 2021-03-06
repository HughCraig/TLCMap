@extends('templates.layout')

@section('content')
    <script>
      $(document).ready( function () {
            $("#dataitemtable").dataTable({
                orderClasses: false,
                bPaginate: true,
                bFilter: true,
                bInfo: false,
                bSortable: true,
                bRetrieve: true,
                aaSorting: [[ 0, "asc" ]], 
                "pageLength": 25,
                aoColumnDefs: [{ "aTargets": [ 13 ], "bSortable": false, "bSearchable": false }]
            }); 
        });
    </script>

    <h2>Layer</h2>
    <!--
    <a href="{{route('publicdatasets')}}" class="btn btn-primary">All Layers</a>
    -->
    <div class="mt-4 mb-1"><p>Note: Layers are contributed from many sources by many people or derived by computer 
                and are the responsibility of the contributor.
                Layers may be incomplete and locations and dates may be imprecise.
                Check the layer for details about the source. Absence in TLCMap does not indicate absence in reality. 
                Use of TLCMap may inform heritage research but is not a substitute for established formal and legal processes and consultation.</p>
            </div>
    <!-- Export/Download -->
    <div class="dropdown">
        <button class="btn btn-secondary dropdown-toggle tlcmgreen" type="button" id="downloadDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            Download
        </button>
        <div class="dropdown-menu" aria-labelledby="downloadDropdown">
            <a class="dropdown-item grab-hover" href="{{url()->full()}}/kml/download">KML</a>
            <a class="dropdown-item grab-hover" href="{{url()->full()}}/csv/download">CSV</a>
            <a class="dropdown-item grab-hover" href="{{url()->full()}}/json/download">GeoJSON</a>
        </div>
    </div>

    

        <!-- Web Services Feed -->
        <div class="dropdown">
        <button class="btn btn-secondary dropdown-toggle tlcmgreen" type="button" id="wsfeedDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            WS Feed
        </button>
        <div class="dropdown-menu" aria-labelledby="wsfeedDropdown">
            <a class="dropdown-item grab-hover" href="{{url()->full()}}/kml">KML</a>
            <a class="dropdown-item grab-hover" href="{{url()->full()}}/csv">CSV</a>
            <a class="dropdown-item grab-hover" href="{{url()->full()}}/json">GeoJSON</a>
        </div>
        </div>

    <!-- Visualise-->
    <div class="dropdown">
        <button class="btn btn-secondary dropdown-toggle tlcmorange" type="button" id="visualiseDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        ???? View Maps...
        </button>
        <div class="dropdown-menu" aria-labelledby="visualiseDropdown">
            <a class="dropdown-item grab-hover" onclick="window.open('/view/3d.html?load={{url()->full()}}/json')">3D Viewer</a>
            <a class="dropdown-item grab-hover" onclick="window.open('/view/cluster.html?load={{url()->full()}}/json')">Cluster</a>
            <a class="dropdown-item grab-hover" onclick="window.open('/view/journey.html?line=route&load={{url()->full()}}/json')">Journey Route</a>
            <a class="dropdown-item grab-hover" onclick="window.open('/view/journey.html?line=time&load={{url()->full()}}/json')">Journey Times</a>
            <a class="dropdown-item grab-hover" onclick="window.open('/view/timeline.html?load={{url()->full()}}/json?sort=start')">Timeline</a>
            <a class="dropdown-item grab-hover" onclick="window.open('/view/werekata.html?load={{url()->full()}}/json')">Werekata Flight by Route</a>
            <a class="dropdown-item grab-hover" onclick="window.open('/view/werekata.html?sort=start&load={{url()->full()}}/json')">Werekata Flight by Time</a>
            <a class="dropdown-item grab-hover" onclick="window.open('/te/?file={{url()->full()}}/kml')">Temporal Earth</a>
        </div>
    </div>

    <!-- Quick Info -->
    <div class="row mt-3">
        <div class="col-lg-4">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <tr><th class="w-25">Name</th><td>{{$ds->name}}</td></tr>
		    <tr style="height: 50px; overflow: auto"><th>Description</th><td>{{$ds->description}}</td></tr>
		    <tr><th>Type</th><td>{{$ds->type}}</td></tr>
<tr style="height: 50px; overflow: auto"><th>Content Warning</th><td>{{$ds->warning}}</td></tr>
                    <tr><th>Contributor</th><td>{{$ds->ownerName()}}</td></tr>
                    <tr><th>Entries</th><td id="dscount">{{count($ds->dataitems)}}</td></tr>
                    <tr><th>Allow ANPS?</th><td id="dspublic">@if($ds->allowanps)Yes @else No @endif</td></tr>
                    <tr><th>Added to System</th><td>{{$ds->created_at}}</td></tr>
                    <tr><th>Updated in System</th><td id="dsupdatedat">{{$ds->updated_at}}</td></tr>
                </table>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="table-responsive" style="overflow: unset">
                <table class="table table-bordered">
                <tr><th class="w-25">Subject</th>
                    <td>
                        @for($i = 0; $i < count($ds->subjectkeywords); $i++)
                            @if($i == count($ds->subjectkeywords)-1)
                            {{$ds->subjectkeywords[$i]->keyword}}
                            @else
                            {{$ds->subjectkeywords[$i]->keyword}},
                            @endif
                        @endfor
                    </td>
                </tr>
                    <tr><th>Creator</th><td>{{$ds->creator}}</td></tr>
                    <tr><th>Publisher</th><td>{{$ds->publisher}}</td></tr>
                    <tr><th>Contact</th><td>{{$ds->contact}}</td></tr>
                    <tr><th>Citation</th><td>{{$ds->citation}}</td></tr>
                    <tr><th>DOI</th><td id="doi">{{$ds->doi}}</td></tr>
                    <tr><th>Source URL</th><td id="source_url">{{$ds->source_url}}</td></tr>
                    <tr><th>Date From</th><td>{{$ds->temporal_from}}</td></tr>
                    <tr><th>Date To</th><td>{{$ds->temporal_to}}</td></tr> 
                </table>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <tr><th class="w-25">Latitude From</th><td>{{$ds->latitude_from}}</td></tr>
                    <tr><th>Longitude From</th><td>{{$ds->longitude_from}}</td></tr>
                    <tr><th>Latitude To</th><td>{{$ds->latitude_to}}</td></tr>
                    <tr><th>Longitude To</th><td>{{$ds->longitude_to}}</td></tr>
                    <tr><th>Language</th><td>{{$ds->language}}</td></tr>
                    <tr><th>License</th><td>{{$ds->license}}</td></tr>
                    <tr><th>Rights</th><td>{{$ds->rights}}</td></tr>
                    <tr><th>Date Created (externally)</th><td>{{$ds->created}}</td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Dataitem Table -->


    <div class="container">


  <!--
    <table id="dataitemtable" class="display" style="width:100%">
  
        <thead class="w3-black"><tr>
            <th>Title</th><th>Placename</th><th>Type</th><th>Description</th><th>Latitude</th><th>Longitude</th><th>Date Start</th><th>Date End</th><th>State</th><th>Feature Term</th><th>LGA</th><th>Parish</th><th>Source</th><th>URL</th><th>Visualise</th><th>Created</th><th>Updated</th>
        </tr></thead>
        <tbody>
    -->
        @foreach($ds->dataitems as $data)



        <div class="row">
            <div class="col">

                <dl>
<!--
	    <tr id="row_id_{{$data->id}}">
    -->



    <h4><button type="button" class="btn btn-primary btn-sm" onclick="copyLink('t{{base_convert($data->id,10,16)}}',this,'id')">C</button>
                <a href="{{env('APP_URL')}}/search?id=t{{base_convert($data->id,10,16)}}">
                @if(isset($data->title)){{$data->title}}@else{{$data->placename}}@endif</a>
                </h4>
                <dl>
                @if(isset($data->placename))<dt>Placename</dt><dd>{{$data->placename}}</dd>@endif
                <dl>
                
                @if(isset($data->recordtype_id))<dt>Type</dt><dd>{{$data->recordtype->type}}</dd>
                @elseif(isset($data->dataset->recordtype_id))<dt>Type</dt><dd>{{$data->dataset->recordtype->type}}</dd>
                @endif

                
                    <div class="dropdown">
                        <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        ???? View Maps...
                        </button>
                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            <a class="dropdown-item grab-hover" 
                                onclick="window.open(`/3d/places.html?load={{env('APP_URL')}}/search?id%3Dt{{base_convert($data->id,10,16)}}%26format%3Djson`)">3D Viewer</a>
                            <a class="dropdown-item grab-hover" onclick="window.open('https\:\/\/www.google.com/maps/search/?api=1&query={{$data->latitude}},{{$data->longitude}}')">Google Maps</a>
                            @if(isset($data->placename)) <a class="dropdown-item grab-hover" target="_blank" href="https://trove.nla.gov.au/search?keyword={{$data->placename}}">Trove Search</a>
                                @else<a class="dropdown-item grab-hover" target="_blank" href="https://trove.nla.gov.au/search?keyword={{$data->title}}">Trove Search</a>@endif
                        
                        </div>
                    </div>
                

            </div>
            <div class="col">

                <h4>Details</h4>

                @if(isset($data->latitude))<dt>Latitude</dt><dd>{{$data->latitude}}</dd>@endif
            @if(isset($data->longitude))<dt>Longitude</dt><dd>{{$data->longitude}}</dd>@endif
            @if(isset($data->start))<dt>Start Date</dt><dd>{{$data->start}}</dd>@endif
            @if(isset($data->end))<dt>End Date</dt><dd>{{$data->end}}</dd>@endif

            @if(isset($data->state_code))<dt>State</dt><dd>{{$data->state_code}}</dd>@endif
            @if(isset($data->lga_name))<dt>LGA</dt><dd>{{$data->lga_name}}</dd>@endif
            @if(isset($data->parish))<dt>Parish</dt><dd>{{$data->parish}}</dd>@endif
            @if(isset($data->feature_term))<dt>Feature Term</dt><dd>{{$data->feature_term}}</dd>@endif


            


                    </div>
            <div class="col">

            <h4>Description</h4>
            @if(isset($data->description))
                    <div>{!!$data->description!!}</div>@endif


            @if(isset($data->extended_data))
                </div>
                <div class="col">
                <h4>Extended Data</h4>
                {!!$data->extDataAsHTML()!!}
            @endif




            </div>
            <div class="col">
                <h4>Sources</h4>
                @if(isset($data->id))<dt>TLCMap ID</dt><dd>{{$data->id}}</dd>@endif
                @if(isset($data->external_url))<dt>Linkback</dt><dd><a href="{{$data->external_url}}">{{$data->external_url}}</a></dd>@endif
                @if(isset($data->source))<dt>Source</dt><dd>{{$data->source}}</dd>@endif

                @if(isset($data->created_at))<dt>Created At</dt><dd>{{$data->created_at}}</dd>@endif
                @if(isset($data->updated_at))<dt id="updatedat">Updated At</dt><dd>{{$data->updated_at}}</dd>@endif

            </div>
            <!-- end bootstrap row -->
            </div>

        @endforeach
<!-- end bootstrap container -->
</div>


    <a href="{{ route('publicdatasets') }}" class="mb-3 btn btn-primary">All Layers</a>
    <script src="http://localhost:8090/ghap/js/savesearch.js"></script> <!-- for copy link -->

@endsection
