<div class="mb-4">
    <h4 class="fw-bold text-dark mb-1">My Profile</h4>
    <p class="text-secondary text-sm">View and manage your personal information.</p>
</div>
<div class="card bg-white custom-card shadow-sm p-4" style="max-width: 800px;">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label text-secondary" style="font-size: 13px;">Full Name</label>
            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($student['full_name']); ?>" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label text-secondary" style="font-size: 13px;">Email Address</label>
            <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($student['email']); ?>" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label text-secondary" style="font-size: 13px;">Matric No.</label>
            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($student['matric_no'] ?? ''); ?>" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label text-secondary" style="font-size: 13px;">Semester</label>
            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($student['semester'] ?? 'Sem 5'); ?>" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label text-secondary" style="font-size: 13px;">Phone Number</label>
            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($student['phone'] ?? 'Not provided'); ?>" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label text-secondary" style="font-size: 13px;">Programme / Department</label>
            <input type="text" class="form-control bg-light" value="DIT - Jabatan Teknologi Maklumat" readonly>
        </div>
        <!-- Added Academic Advisor (PA) field -->
        <div class="col-md-6">
            <label class="form-label text-secondary" style="font-size: 13px;">Academic Advisor (PA)</label>
            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($student['pa_name'] ?? 'Not Assigned'); ?>" readonly>
        </div>
    </div>
</div>