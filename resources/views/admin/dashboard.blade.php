@extends('layouts.admin')
@section('content')
    <section class="content">
        <div class="container-fluid">
            <!-- ====== STATS ROW ====== -->
            <div class="row g-4 mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-modern p-3 h-100 border-0 bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-secondary text-uppercase small fw-semibold">Total policies</span>
                                <h3 class="fw-bold mt-1 mb-0">1,284</h3>
                                <span class="text-success small"><i class="fas fa-arrow-up me-1"></i>+12%</span>
                            </div>
                            <div class="stat-icon bg-soft-primary"><i class="fas fa-book"></i></div>
                        </div>
                        <div class="mt-2 small text-secondary"><i class="far fa-calendar-alt me-1"></i> updated today</div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-modern p-3 h-100 border-0 bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-secondary text-uppercase small fw-semibold">Active</span>
                                <h3 class="fw-bold mt-1 mb-0">847</h3>
                                <span class="text-success small"><i class="fas fa-arrow-up me-1"></i>+5%</span>
                            </div>
                            <div class="stat-icon bg-soft-success"><i class="fas fa-check-circle"></i></div>
                        </div>
                        <div class="mt-2 small text-secondary"><i class="far fa-clock me-1"></i> last 30d</div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-modern p-3 h-100 border-0 bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-secondary text-uppercase small fw-semibold">Pending</span>
                                <h3 class="fw-bold mt-1 mb-0">36</h3>
                                <span class="text-warning small"><i class="fas fa-minus me-1"></i>2</span>
                            </div>
                            <div class="stat-icon bg-soft-warning"><i class="fas fa-hourglass-half"></i></div>
                        </div>
                        <div class="mt-2 small text-secondary"><i class="fas fa-rotate-right me-1"></i> review needed</div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-modern p-3 h-100 border-0 bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-secondary text-uppercase small fw-semibold">Archived</span>
                                <h3 class="fw-bold mt-1 mb-0">401</h3>
                                <span class="text-secondary small"><i class="fas fa-minus me-1"></i>0</span>
                            </div>
                            <div class="stat-icon bg-soft-purple"><i class="fas fa-archive"></i></div>
                        </div>
                        <div class="mt-2 small text-secondary"><i class="fas fa-folder me-1"></i> since 2024</div>
                    </div>
                </div>
            </div>

            <!-- ====== TOOLBAR + TABLE ====== -->
            <div class="card card-modern border-0 shadow-sm bg-white">
                <div class="card-header bg-transparent border-0 d-flex flex-wrap align-items-center justify-content-between pt-4 px-4">
                    <h5 class="fw-bold mb-0"><i class="fas fa-list-ul text-primary me-2"></i>Recent policies</h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="fas fa-file-export me-1"></i> Export</button>
                        <button class="btn btn-sm btn-primary rounded-pill px-4"><i class="fas fa-plus me-1"></i> New</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-policy mb-0">
                            <thead class="bg-light bg-opacity-50">
                                <tr>
                                    <th class="ps-4 py-3 fw-semibold text-secondary">Title</th>
                                    <th class="py-3 fw-semibold text-secondary">Category</th>
                                    <th class="py-3 fw-semibold text-secondary">Status</th>
                                    <th class="py-3 fw-semibold text-secondary">Last updated</th>
                                    <th class="pe-4 py-3 fw-semibold text-secondary text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-demo me-2" style="width: 32px; height: 32px; background: #2563eb; font-size: 0.7rem;">P</div>
                                            <div><span class="fw-semibold">Data Privacy v3.2</span><br><span class="small text-secondary">GDPR compliance</span></div>
                                        </div>
                                    </td>
                                    <td class="py-3"><span class="badge bg-light text-dark rounded-pill px-3 py-2">Compliance</span></td>
                                    <td class="py-3"><span class="badge-policy badge bg-success bg-opacity-10 text-success">Active</span></td>
                                    <td class="py-3 text-secondary">2 hours ago</td>
                                    <td class="pe-4 py-3 text-end">
                                        <a href="#" class="text-secondary me-2" title="view"><i class="fas fa-eye"></i></a>
                                        <a href="#" class="text-secondary me-2" title="edit"><i class="fas fa-pen"></i></a>
                                        <a href="#" class="text-secondary" title="more"><i class="fas fa-ellipsis-v"></i></a>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-demo me-2" style="width: 32px; height: 32px; background: #7c3aed; font-size: 0.7rem;">S</div>
                                            <div><span class="fw-semibold">Security Incident Response</span><br><span class="small text-secondary">SOC 2</span></div>
                                        </div>
                                    </td>
                                    <td class="py-3"><span class="badge bg-light text-dark rounded-pill px-3 py-2">Security</span></td>
                                    <td class="py-3"><span class="badge-policy badge bg-warning bg-opacity-15 text-warning">Pending</span></td>
                                    <td class="py-3 text-secondary">Yesterday</td>
                                    <td class="pe-4 py-3 text-end">
                                        <a href="#" class="text-secondary me-2"><i class="fas fa-eye"></i></a>
                                        <a href="#" class="text-secondary me-2"><i class="fas fa-pen"></i></a>
                                        <a href="#" class="text-secondary"><i class="fas fa-ellipsis-v"></i></a>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-demo me-2" style="width: 32px; height: 32px; background: #0b7e4b; font-size: 0.7rem;">R</div>
                                            <div><span class="fw-semibold">Remote Work Policy</span><br><span class="small text-secondary">HR</span></div>
                                        </div>
                                    </td>
                                    <td class="py-3"><span class="badge bg-light text-dark rounded-pill px-3 py-2">Workplace</span></td>
                                    <td class="py-3"><span class="badge-policy badge bg-success bg-opacity-10 text-success">Active</span></td>
                                    <td class="py-3 text-secondary">3 days ago</td>
                                    <td class="pe-4 py-3 text-end">
                                        <a href="#" class="text-secondary me-2"><i class="fas fa-eye"></i></a>
                                        <a href="#" class="text-secondary me-2"><i class="fas fa-pen"></i></a>
                                        <a href="#" class="text-secondary"><i class="fas fa-ellipsis-v"></i></a>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-demo me-2" style="width: 32px; height: 32px; background: #b45309; font-size: 0.7rem;">A</div>
                                            <div><span class="fw-semibold">Access Control Matrix</span><br><span class="small text-secondary">IAM</span></div>
                                        </div>
                                    </td>
                                    <td class="py-3"><span class="badge bg-light text-dark rounded-pill px-3 py-2">IT</span></td>
                                    <td class="py-3"><span class="badge-policy badge bg-secondary bg-opacity-15 text-secondary">Archived</span></td>
                                    <td class="py-3 text-secondary">Dec 12, 2025</td>
                                    <td class="pe-4 py-3 text-end">
                                        <a href="#" class="text-secondary me-2"><i class="fas fa-eye"></i></a>
                                        <a href="#" class="text-secondary me-2"><i class="fas fa-pen"></i></a>
                                        <a href="#" class="text-secondary"><i class="fas fa-ellipsis-v"></i></a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 d-flex justify-content-between align-items-center px-4 py-3">
                    <span class="text-secondary small">Showing 4 of 1,284 policies</span>
                    <nav aria-label="pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item disabled"><a class="page-link rounded-3" href="#">Previous</a></li>
                            <li class="page-item active"><a class="page-link rounded-3" href="#">1</a></li>
                            <li class="page-item"><a class="page-link rounded-3" href="#">2</a></li>
                            <li class="page-item"><a class="page-link rounded-3" href="#">3</a></li>
                            <li class="page-item"><a class="page-link rounded-3" href="#">Next</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </section>
@endsection
@section('scripts')
@endsection