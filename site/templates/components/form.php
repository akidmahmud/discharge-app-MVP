<form action="#" method="post">
  <div class="form-section">
    <h2>Patient Information</h2>
    <div class="form-row">
      <div class="form-col">
        <div class="field-group">
          <label for="patient-name">Name</label>
          <input class="input" id="patient-name" name="patient_name" type="text" placeholder="Enter patient name">
        </div>
      </div>
      <div class="form-col">
        <div class="field-group">
          <label for="patient-age">Age</label>
          <input class="input" id="patient-age" name="patient_age" type="number" placeholder="Enter age">
        </div>
      </div>
      <div class="form-col">
        <div class="field-group">
          <label for="patient-gender">Gender</label>
          <select class="select" id="patient-gender" name="patient_gender">
            <option value="">Select gender</option>
            <option>Female</option>
            <option>Male</option>
            <option>Other</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div class="form-section">
    <h2>Admission Details</h2>
    <div class="form-row">
      <div class="form-col">
        <div class="field-group">
          <label for="admission-date">Admission Date</label>
          <input class="input" id="admission-date" name="admission_date" type="date">
        </div>
      </div>
      <div class="form-col">
        <div class="field-group">
          <label for="consultant">Consultant</label>
          <select class="select" id="consultant" name="consultant">
            <option value="">Select consultant</option>
            <option>Dr. Farhana Islam</option>
            <option>Dr. Rezaul Karim</option>
            <option>Dr. Samia Hossain</option>
          </select>
        </div>
      </div>
      <div class="form-col">
        <div class="field-group">
          <label for="ward">Ward</label>
          <input class="input" id="ward" name="ward" type="text" placeholder="Enter ward">
        </div>
      </div>
    </div>
  </div>

  <div class="form-section">
    <h2>Clinical Details</h2>
    <div class="form-row">
      <div class="form-col">
        <div class="field-group">
          <label for="diagnosis">Diagnosis</label>
          <textarea class="textarea" id="diagnosis" name="diagnosis" placeholder="Enter diagnosis"></textarea>
        </div>
      </div>
    </div>
    <div class="form-row">
      <div class="form-col">
        <div class="field-group">
          <label for="notes">Notes</label>
          <textarea class="textarea" id="notes" name="notes" placeholder="Add clinical notes"></textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="form-actions">
    <button class="btn btn-secondary" type="button">Cancel</button>
    <button class="btn btn-primary" type="submit">Save</button>
  </div>
</form>
