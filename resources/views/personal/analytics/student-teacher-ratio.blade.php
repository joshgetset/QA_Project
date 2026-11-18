<section class="qams-hrmis-section" aria-labelledby="hrmis-ratio-title">
    <h5 class="qams-ces-section-title mb-3" id="hrmis-ratio-title">Student-to-Teacher Ratio</h5>

    @if ($studentTeacherRatio === null)
        <p class="alert alert-secondary mb-0">Enrollment data is unavailable for the selected reporting year.</p>
    @else
        @php
            $year = $studentTeacherRatio['year'];
            $semester = match ($studentTeacherRatio['semester']) {
                '1' => 'Semester 1',
                '2' => 'Semester 2',
                '9' => 'Summer',
                default => 'Semester ' . $studentTeacherRatio['semester'],
            };
        @endphp
        <div class="card qams-ces-demographic-overview" data-hrmis-ratio>
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                    <div>
                        <p class="text-body-secondary mb-1">Enrolled students per teaching employee across the six campuses.</p>
                        <span class="small text-body-secondary">AY {{ $year }}–{{ $year + 1 }} · {{ $semester }}</span>
                    </div>
                    <div>
                        <label class="form-label qams-ces-filter-label" for="hrmis-ratio-scope">View</label>
                        <select class="form-select qams-ces-filter-select" id="hrmis-ratio-scope" data-hrmis-ratio-scope>
                            <option value="campuses">By Campus</option>
                            <option value="university">Whole University</option>
                        </select>
                    </div>
                </div>
                <script type="application/json">@json(['campuses' => $studentTeacherRatio['campuses'], 'university' => $studentTeacherRatio['university']])</script>
                <div data-hrmis-ratio-chart></div>
                <details class="mt-2">
                    <summary class="small fw-semibold text-primary">View enrollment and teaching counts</summary>
                    @foreach (['campuses', 'university'] as $scope)
                        <div class="table-responsive" data-hrmis-ratio-table="{{ $scope }}" @if ($scope !== 'campuses') hidden @endif>
                            <table class="table table-sm qams-hrmis-ratio-table mb-0" aria-label="Student-to-teacher ratio">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ $scope === 'campuses' ? 'Campus' : 'Institution' }}</th>
                                        <th scope="col" class="text-end">Student Enrollment</th>
                                        <th scope="col" class="text-end">Teaching Personnel</th>
                                        <th scope="col" class="text-end">Students per Teacher</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($studentTeacherRatio[$scope] as $row)
                                        <tr>
                                            <th scope="row">{{ $row['label'] }}</th>
                                            <td class="text-end">{{ $row['students'] === null ? '—' : number_format($row['students']) }}</td>
                                            <td class="text-end">{{ $row['teachers'] === null ? '—' : number_format($row['teachers']) }}</td>
                                            <td class="text-end">{{ $row['ratio'] === null ? '—' : number_format($row['ratio'], 2) . ':1' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </details>
            </div>
        </div>
    @endif
</section>
