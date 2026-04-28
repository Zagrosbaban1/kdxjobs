const { useEffect, useMemo, useState } = React;

const API = "api/index.php";
const emptyData = { jobs: [], companies: [], applications: [], stats: {} };

async function api(action, body, options = {}) {
  const response = await fetch(`${API}?action=${action}`, {
    method: body ? "POST" : "GET",
    headers: body instanceof FormData ? undefined : { "Content-Type": "application/json" },
    body: body instanceof FormData ? body : body ? JSON.stringify(body) : undefined,
    ...options,
  });
  const data = await response.json();
  if (!response.ok || !data.ok) throw new Error(data.error || "Request failed");
  return data;
}

function Icon({ children, small = false }) {
  return (
    <span className={`inline-flex items-center justify-center rounded-lg bg-sky-50 text-sky-600 ${small ? "h-8 w-8 text-sm" : "h-12 w-12 text-xl"}`}>
      {children}
    </span>
  );
}

function Button({ children, onClick, variant = "primary", className = "", type = "button", disabled = false }) {
  const styles =
    variant === "outline"
      ? "border border-sky-200 bg-white text-sky-700 hover:bg-sky-50"
      : "bg-sky-500 text-white hover:bg-sky-600";

  return (
    <button
      type={type}
      disabled={disabled}
      onClick={onClick}
      className={`inline-flex items-center justify-center gap-2 rounded-lg px-5 py-3 font-semibold transition disabled:cursor-not-allowed disabled:opacity-60 ${styles} ${className}`}
    >
      {children}
    </button>
  );
}

function Card({ children, className = "" }) {
  return <div className={`rounded-lg border border-sky-100 bg-white shadow-sm ${className}`}>{children}</div>;
}

function SectionTitle({ eyebrow, title, subtitle }) {
  return (
    <div className="mx-auto mb-10 max-w-3xl text-center">
      <p className="mb-2 text-sm font-semibold uppercase text-sky-500">{eyebrow}</p>
      <h2 className="text-3xl font-bold text-slate-900 md:text-4xl">{title}</h2>
      <p className="mt-3 text-slate-600">{subtitle}</p>
    </div>
  );
}

function StatCard({ icon, label, value }) {
  return (
    <Card className="bg-white/90">
      <div className="flex items-center gap-4 p-5">
        <Icon>{icon}</Icon>
        <div>
          <p className="text-sm text-slate-500">{label}</p>
          <p className="text-2xl font-bold text-slate-900">{value ?? 0}</p>
        </div>
      </div>
    </Card>
  );
}

function JobCard({ job, onSelect, selected }) {
  return (
    <Card className={`h-full transition hover:-translate-y-1 hover:shadow-xl ${selected ? "ring-2 ring-sky-300" : ""}`}>
      <div className="p-6">
        <div className="mb-5 flex items-start justify-between gap-4">
          <Icon>💼</Icon>
          <span className="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">{job.type}</span>
        </div>
        <h3 className="text-xl font-bold text-slate-900">{job.title}</h3>
        <p className="mt-1 font-medium text-slate-600">{job.company}</p>
        <div className="mt-4 space-y-2 text-sm text-slate-500">
          <p>📍 {job.location}</p>
          <p>💰 {job.salary}</p>
        </div>
        <div className="mt-5 flex flex-wrap gap-2">
          {(job.tags || []).map((tag) => (
            <span key={tag} className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{tag}</span>
          ))}
        </div>
        <Button className="mt-6 w-full" onClick={() => onSelect?.(job)}>View Details</Button>
      </div>
    </Card>
  );
}

function Input({ label, name, placeholder, type = "text", value, defaultValue, onChange, required = false }) {
  return (
    <label className="block">
      <span className="mb-2 block text-sm font-semibold text-slate-700">{label}</span>
      <input
        name={name}
        type={type}
        value={value}
        defaultValue={defaultValue}
        required={required}
        onChange={onChange}
        placeholder={placeholder}
        className="w-full rounded-lg border border-sky-100 bg-white px-4 py-3 text-slate-700 outline-none transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100"
      />
    </label>
  );
}

function Message({ message }) {
  if (!message) return null;
  const color = message.type === "error" ? "border-red-100 bg-red-50 text-red-700" : "border-emerald-100 bg-emerald-50 text-emerald-700";
  return <div className={`mb-5 rounded-lg border px-4 py-3 text-sm font-semibold ${color}`}>{message.text}</div>;
}

function HomePage({ setPage, data, setSelectedJob }) {
  const latestApplicants = data.applications.slice(0, 3);
  return (
    <>
      <section className="relative overflow-hidden px-6 py-20 md:py-28">
        <div className="mx-auto grid max-w-7xl items-center gap-12 md:grid-cols-2">
          <div>
            <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-sky-100 bg-white px-4 py-2 text-sm font-semibold text-sky-700 shadow-sm">
              🛡️ Smart Recruitment Platform
            </span>
            <h1 className="text-4xl font-extrabold leading-tight text-slate-950 md:text-6xl">
              Find the right job. Hire the right talent.
            </h1>
            <p className="mt-6 max-w-xl text-lg leading-8 text-slate-600">
              A clean recruitment website for job seekers, companies, and admins, connected to a MySQL database through PHP APIs.
            </p>
            <div className="mt-8 flex flex-col gap-3 rounded-lg border border-sky-100 bg-white p-3 shadow-lg md:flex-row">
              <div className="flex flex-1 items-center gap-3 px-3">
                <span className="text-sky-500">🔍</span>
                <input className="w-full bg-transparent py-3 text-slate-700 outline-none" placeholder="Search job title, company, or skill" />
              </div>
              <Button onClick={() => setPage("jobs")}>Find Jobs</Button>
            </div>
            <div className="mt-6 flex flex-wrap gap-3">
              <Button onClick={() => setPage("jobs")} className="bg-slate-900 hover:bg-slate-800">Find Jobs</Button>
              <Button onClick={() => setPage("company")} variant="outline">Post a Job</Button>
            </div>
          </div>

          <Card className="bg-white/90 p-3 shadow-2xl">
            <div className="rounded-lg bg-gradient-to-br from-sky-50 to-white p-6">
              <div className="grid gap-4">
                <StatCard icon="💼" label="Open Jobs" value={data.stats.openJobs} />
                <StatCard icon="🏢" label="Companies" value={data.stats.companies} />
                <StatCard icon="👥" label="Job Seekers" value={data.stats.jobSeekers} />
              </div>
              <div className="mt-6 rounded-lg bg-white p-5 shadow-sm">
                <p className="mb-4 font-bold text-slate-900">Latest Applicants</p>
                {latestApplicants.map((a) => (
                  <div key={a.id} className="mb-3 flex items-center justify-between rounded-lg bg-slate-50 p-3 last:mb-0">
                    <div>
                      <p className="font-semibold text-slate-800">{a.applicant_name}</p>
                      <p className="text-sm text-slate-500">{a.role}</p>
                    </div>
                    <span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">{a.status}</span>
                  </div>
                ))}
              </div>
            </div>
          </Card>
        </div>
      </section>

      <section className="px-6 py-16">
        <SectionTitle eyebrow="Featured Jobs" title="Fresh opportunities for talented people" subtitle="These cards come directly from the jobs table." />
        <div className="mx-auto grid max-w-7xl gap-6 md:grid-cols-3">
          {data.jobs.slice(0, 3).map((job) => (
            <JobCard key={job.id} job={job} onSelect={(selected) => { setSelectedJob(selected); setPage("jobs"); }} />
          ))}
        </div>
      </section>
    </>
  );
}

function AuthPage({ mode, reload, setCurrentUser, setPage }) {
  const isRegister = mode === "register";
  const [role, setRole] = useState("jobseeker");
  const [message, setMessage] = useState(null);
  const [loading, setLoading] = useState(false);

  async function submit(event) {
    event.preventDefault();
    setLoading(true);
    setMessage(null);
    const form = new FormData(event.currentTarget);

    try {
      if (isRegister) {
        form.set("role", role);
        const result = await api("register", form);
        setMessage({ type: "success", text: result.message });
        event.currentTarget.reset();
        await reload();
      } else {
        const result = await api("login", Object.fromEntries(form.entries()));
        localStorage.setItem("recruitpro_user", JSON.stringify(result.user));
        setCurrentUser(result.user);
        setMessage({ type: "success", text: result.message });
        setPage(result.user.role === "admin" ? "admin" : result.user.role === "company" ? "company" : "user");
      }
    } catch (error) {
      setMessage({ type: "error", text: error.message });
    } finally {
      setLoading(false);
    }
  }

  return (
    <section className="px-6 py-16">
      <div className="mx-auto grid max-w-6xl items-center gap-10 md:grid-cols-2">
        <div>
          <p className="mb-3 text-sm font-semibold uppercase text-sky-500">{isRegister ? "Create Account" : "Welcome Back"}</p>
          <h1 className="text-4xl font-extrabold text-slate-950">{isRegister ? "Join the recruitment platform" : "Login to your dashboard"}</h1>
          <p className="mt-4 text-slate-600">Demo accounts use password <strong>password</strong>: zagros@example.com, company@example.com, admin@example.com.</p>
        </div>
        <Card className="shadow-xl">
          <form className="p-7" onSubmit={submit}>
            <Message message={message} />
            {isRegister && (
              <div className="mb-6 grid grid-cols-2 gap-3 rounded-lg bg-sky-50 p-2">
                <button type="button" onClick={() => setRole("jobseeker")} className={`rounded-lg px-4 py-3 text-sm font-bold ${role === "jobseeker" ? "bg-white text-sky-700 shadow" : "text-slate-500"}`}>Job Seeker</button>
                <button type="button" onClick={() => setRole("company")} className={`rounded-lg px-4 py-3 text-sm font-bold ${role === "company" ? "bg-white text-sky-700 shadow" : "text-slate-500"}`}>Company</button>
              </div>
            )}
            <div className="space-y-4">
              {isRegister && role === "jobseeker" && <Input required label="Full Name" name="full_name" placeholder="Your full name" />}
              {isRegister && role === "company" && <Input required label="Company Name" name="company_name" placeholder="Company name" />}
              <Input required label="Email" name="email" placeholder="example@email.com" type="email" />
              {isRegister && <Input label="Phone" name="phone" placeholder="+964 750 000 0000" />}
              {isRegister && role === "jobseeker" && <Input label="Skills" name="skills" placeholder="SQL, Excel, React, HR..." />}
              {isRegister && role === "company" && <Input required label="Industry" name="industry" placeholder="Technology, FMCG, Finance..." />}
              {isRegister && role === "company" && <Input required label="Location" name="location" placeholder="Erbil, Baghdad, Remote..." />}
              <Input required label="Password" name="password" placeholder="password" type="password" />
              {isRegister && role === "jobseeker" && <Input label="Upload CV" name="cv_file" type="file" />}
              {isRegister && role === "company" && <Input label="Company Logo" name="logo_file" type="file" />}
            </div>
            <Button disabled={loading} type="submit" className="mt-6 w-full py-4">{loading ? "Saving..." : isRegister ? "Create Account" : "Login"}</Button>
          </form>
        </Card>
      </div>
    </section>
  );
}

function DashboardShell({ type, data, reload, currentUser }) {
  const isUser = type === "user";
  const isCompany = type === "company";
  const title = isUser ? "Job Seeker Dashboard" : isCompany ? "Company Dashboard" : "Admin Dashboard";
  const subtitle = isUser ? "Manage your profile, CV, saved jobs, and applications." : isCompany ? "Manage your company profile, job posts, and applicants." : "Control users, companies, job posts, approvals, and statistics.";
  const company = data.companies.find((c) => Number(c.user_id) === Number(currentUser?.id)) || data.companies[0];
  const visibleApplications = isUser && currentUser ? data.applications.filter((a) => a.applicant_email === currentUser.email) : data.applications;

  async function updateStatus(application_id, status) {
    await api("update-application", { application_id, status });
    await reload();
  }

  return (
    <section className="px-6 py-12">
      <div className="mx-auto max-w-7xl">
        <div className="mb-8 rounded-lg bg-gradient-to-r from-sky-500 to-cyan-500 p-8 text-white shadow-xl">
          <h1 className="text-3xl font-extrabold md:text-4xl">{title}</h1>
          <p className="mt-2 max-w-2xl text-sky-50">{subtitle}</p>
        </div>

        <div className="grid gap-6 md:grid-cols-4">
          <aside className="rounded-lg border border-sky-100 bg-white p-5 shadow-sm md:col-span-1">
            <div className="mb-6 flex items-center gap-3">
              <Icon>{isUser ? "👤" : isCompany ? "🏢" : "🛡️"}</Icon>
              <div>
                <p className="font-bold text-slate-900">{currentUser?.full_name || currentUser?.company_name || (isCompany ? company?.name : "Admin")}</p>
                <p className="text-sm text-slate-500">{isUser ? "Job Seeker" : isCompany ? "Company Account" : "Platform Manager"}</p>
              </div>
            </div>
            {["Profile", "Applications", "Manage", "Statistics", "Settings"].map((item) => (
              <button key={item} className="mb-2 flex w-full items-center gap-3 rounded-lg px-4 py-3 text-left font-medium text-slate-600 transition hover:bg-sky-50 hover:text-sky-700">
                ⚙️ {item}
              </button>
            ))}
          </aside>

          <main className="grid gap-6 md:col-span-3">
            <div className="grid gap-6 md:grid-cols-3">
              <StatCard icon="💼" label={isUser ? "Applied Jobs" : isCompany ? "Active Jobs" : "Users"} value={isUser ? visibleApplications.length : isCompany ? data.jobs.filter((j) => Number(j.company_id) === Number(company?.id)).length : data.stats.users} />
              <StatCard icon={isUser ? "⭐" : isCompany ? "👥" : "🏢"} label={isUser ? "Saved Jobs" : isCompany ? "Applicants" : "Companies"} value={isUser ? 0 : isCompany ? data.applications.length : data.stats.companies} />
              <StatCard icon="📊" label={isUser ? "Profile Score" : isCompany ? "Views" : "Open Jobs"} value={isUser ? "86%" : isCompany ? "Live" : data.stats.openJobs} />
            </div>

            <Card>
              <div className="p-6">
                <div className="mb-5 flex items-center justify-between gap-4">
                  <h2 className="text-2xl font-bold text-slate-900">{isUser ? "My Applications" : isCompany ? "Recent Applicants" : "Management Panel"}</h2>
                </div>

                {isUser && (
                  <div className="space-y-3">
                    {visibleApplications.map((a) => (
                      <div key={a.id} className="rounded-lg bg-sky-50/70 p-4">
                        <p className="font-bold text-slate-800">{a.job_title}</p>
                        <p className="text-sm text-slate-500">{a.company}</p>
                        <p className="mt-2 text-sm font-semibold text-sky-700">{a.status}</p>
                      </div>
                    ))}
                  </div>
                )}

                {isCompany && <CompanyPanel company={company} data={data} reload={reload} updateStatus={updateStatus} />}
                {!isUser && !isCompany && <AdminPanel data={data} updateStatus={updateStatus} />}
              </div>
            </Card>
          </main>
        </div>
      </div>
    </section>
  );
}

function CompanyPanel({ company, data, reload, updateStatus }) {
  const [message, setMessage] = useState(null);
  const [loading, setLoading] = useState(false);

  async function submit(event) {
    event.preventDefault();
    setLoading(true);
    setMessage(null);
    const form = Object.fromEntries(new FormData(event.currentTarget).entries());
    try {
      await api("create-job", { ...form, company_id: company.id });
      setMessage({ type: "success", text: "Job posted successfully." });
      event.currentTarget.reset();
      await reload();
    } catch (error) {
      setMessage({ type: "error", text: error.message });
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <div>
        <h3 className="mb-4 text-lg font-bold text-slate-900">Post New Job</h3>
        <Message message={message} />
        <form className="space-y-3" onSubmit={submit}>
          <Input required label="Title" name="title" placeholder="Backend Developer" />
          <Input required label="Location" name="location" placeholder="Erbil, Remote..." />
          <Input required label="Salary" name="salary" placeholder="$1,200 - $1,800" />
          <Input required label="Type" name="type" placeholder="Full-time, Remote, Hybrid" />
          <Input required label="Description" name="description" placeholder="Describe the role" />
          <Input label="Requirements" name="requirements" placeholder="Skills and requirements" />
          <Input label="Tags" name="tags" placeholder="React, API, SQL" />
          <Button disabled={loading} type="submit" className="w-full">{loading ? "Posting..." : "Post Job"}</Button>
        </form>
      </div>
      <div className="space-y-3">
        <h3 className="mb-4 text-lg font-bold text-slate-900">Applicants</h3>
        {data.applications.slice(0, 5).map((a) => (
          <div key={a.id} className="rounded-lg bg-slate-50 p-4">
            <p className="font-bold text-slate-900">{a.applicant_name}</p>
            <p className="text-sm text-slate-500">Applied for {a.job_title}</p>
            <div className="mt-3 flex flex-wrap gap-2">
              <Button className="bg-emerald-500 px-3 py-2 text-sm hover:bg-emerald-600" onClick={() => updateStatus(a.id, "Accepted")}>✓ Accept</Button>
              <Button variant="outline" className="border-red-100 px-3 py-2 text-sm text-red-600 hover:bg-red-50" onClick={() => updateStatus(a.id, "Rejected")}>✕ Reject</Button>
              <Button variant="outline" className="px-3 py-2 text-sm" onClick={() => updateStatus(a.id, "Shortlisted")}>Shortlist</Button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function AdminPanel({ data, updateStatus }) {
  return (
    <div className="grid gap-4 md:grid-cols-3">
      {[
        ["Manage Users", `${data.stats.users || 0} users`],
        ["Manage Companies", `${data.stats.companies || 0} companies`],
        ["Manage Job Posts", `${data.stats.openJobs || 0} open jobs`],
      ].map(([title, value]) => (
        <div key={title} className="rounded-lg bg-sky-50 p-5">
          <div className="mb-3 text-xl">🛡️</div>
          <p className="font-bold text-slate-900">{title}</p>
          <p className="mt-1 text-sm text-slate-500">{value}</p>
        </div>
      ))}
      <div className="md:col-span-3">
        <h3 className="my-4 text-lg font-bold text-slate-900">Applications</h3>
        <div className="space-y-3">
          {data.applications.map((a) => (
            <div key={a.id} className="flex flex-col justify-between gap-3 rounded-lg bg-slate-50 p-4 md:flex-row md:items-center">
              <div>
                <p className="font-bold text-slate-900">{a.applicant_name}</p>
                <p className="text-sm text-slate-500">{a.job_title} at {a.company}</p>
              </div>
              <select className="rounded-lg border border-sky-100 px-3 py-2" value={a.status} onChange={(event) => updateStatus(a.id, event.target.value)}>
                {["New", "Reviewed", "Shortlisted", "Accepted", "Rejected"].map((status) => <option key={status}>{status}</option>)}
              </select>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function JobsPage({ data, selectedJob, setSelectedJob, reload, currentUser }) {
  const job = selectedJob || data.jobs[0];
  const [message, setMessage] = useState(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!selectedJob && data.jobs[0]) setSelectedJob(data.jobs[0]);
  }, [data.jobs.length]);

  async function submit(event) {
    event.preventDefault();
    setLoading(true);
    setMessage(null);
    const form = Object.fromEntries(new FormData(event.currentTarget).entries());
    try {
      const result = await api("apply", { ...form, job_id: job.id, user_id: currentUser?.id || "" });
      setMessage({ type: "success", text: result.message });
      event.currentTarget.reset();
      await reload();
    } catch (error) {
      setMessage({ type: "error", text: error.message });
    } finally {
      setLoading(false);
    }
  }

  if (!job) return <section className="px-6 py-16 text-center text-slate-600">No jobs found. Install the database seed first.</section>;

  return (
    <section className="px-6 py-12">
      <div className="mx-auto max-w-7xl">
        <SectionTitle eyebrow="Job Details" title="Explore modern job listings" subtitle="Users can view full job information and apply directly to the database." />
        <div className="grid gap-8 md:grid-cols-3">
          <div className="space-y-5 md:col-span-1">
            {data.jobs.map((item) => <JobCard key={item.id} job={item} selected={Number(item.id) === Number(job.id)} onSelect={setSelectedJob} />)}
          </div>
          <Card className="md:col-span-2">
            <div className="p-8">
              <div className="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-start">
                <div>
                  <h1 className="text-3xl font-extrabold text-slate-950">{job.title}</h1>
                  <p className="mt-2 text-lg font-semibold text-sky-600">{job.company}</p>
                </div>
              </div>
              <div className="mb-8 grid gap-4 md:grid-cols-3">
                <Info icon="📍" label="Location" value={job.location} />
                <Info icon="💰" label="Salary" value={job.salary} />
                <Info icon="⏰" label="Job Type" value={job.type} />
              </div>
              <h3 className="text-xl font-bold text-slate-900">Description</h3>
              <p className="mt-3 leading-8 text-slate-600">{job.description}</p>
              <h3 className="mt-8 text-xl font-bold text-slate-900">Requirements</h3>
              <p className="mt-3 leading-8 text-slate-600">{job.requirements}</p>

              <div className="mt-8 rounded-lg bg-sky-50 p-5">
                <h3 className="mb-4 text-xl font-bold text-slate-900">Apply Now</h3>
                <Message message={message} />
                <form className="grid gap-4 md:grid-cols-2" onSubmit={submit}>
                  <Input required label="Full Name" name="applicant_name" placeholder="Your name" defaultValue={currentUser?.full_name || ""} />
                  <Input required label="Email" name="applicant_email" type="email" placeholder="you@email.com" defaultValue={currentUser?.email || ""} />
                  <Input label="Phone" name="applicant_phone" placeholder="+964 750 000 0000" defaultValue={currentUser?.phone || ""} />
                  <Input required label="Role" name="role" placeholder={job.title} />
                  <div className="md:col-span-2">
                    <Input label="Cover Note" name="cover_note" placeholder="Short note for the company" />
                  </div>
                  <Button disabled={loading} type="submit" className="md:col-span-2">{loading ? "Submitting..." : "Submit Application"}</Button>
                </form>
              </div>
            </div>
          </Card>
        </div>
      </div>
    </section>
  );
}

function Info({ icon, label, value }) {
  return (
    <div className="rounded-lg bg-sky-50 p-4">
      <div className="mb-2 text-xl">{icon}</div>
      <p className="text-sm font-semibold text-slate-500">{label}</p>
      <p className="font-bold text-slate-900">{value}</p>
    </div>
  );
}

function CompaniesPage({ data }) {
  return (
    <section className="px-6 py-16">
      <SectionTitle eyebrow="Companies" title="Top companies hiring now" subtitle="Company cards are loaded from the companies table." />
      <div className="mx-auto grid max-w-7xl gap-6 md:grid-cols-3">
        {data.companies.map((c) => (
          <Card key={c.id} className="transition hover:-translate-y-1 hover:shadow-xl">
            <div className="p-7">
              <Icon>🏢</Icon>
              <h3 className="mt-5 text-xl font-bold text-slate-900">{c.name}</h3>
              <p className="mt-1 text-slate-500">{c.industry}</p>
              <p className="mt-1 text-sm text-slate-500">{c.location}</p>
              <p className="mt-5 rounded-lg bg-sky-50 px-4 py-3 font-semibold text-sky-700">{c.jobs} active jobs</p>
              <Button className="mt-5 w-full">View Company</Button>
            </div>
          </Card>
        ))}
      </div>
    </section>
  );
}

function RecruitmentWebsite() {
  const [page, setPage] = useState("home");
  const [open, setOpen] = useState(false);
  const [data, setData] = useState(emptyData);
  const [selectedJob, setSelectedJob] = useState(null);
  const [currentUser, setCurrentUser] = useState(() => {
    try {
      return JSON.parse(localStorage.getItem("recruitpro_user"));
    } catch {
      return null;
    }
  });
  const [status, setStatus] = useState({ loading: true, error: "" });

  async function reload() {
    try {
      setStatus({ loading: true, error: "" });
      const result = await api("bootstrap");
      setData(result);
      setSelectedJob((current) => current || result.jobs[0] || null);
      setStatus({ loading: false, error: "" });
    } catch (error) {
      setStatus({ loading: false, error: error.message });
    }
  }

  useEffect(() => { reload(); }, []);

  const nav = [
    ["home", "Home"],
    ["jobs", "Jobs"],
    ["companies", "Companies"],
    ["login", "Login"],
    ["register", "Register"],
    ["user", "Job Seeker"],
    ["company", "Company"],
    ["admin", "Admin"],
  ];

  const userLabel = useMemo(() => currentUser?.full_name || currentUser?.company_name || currentUser?.email, [currentUser]);

  return (
    <div className="min-h-screen bg-gradient-to-b from-sky-50 via-white to-white text-slate-900">
      <header className="sticky top-0 z-50 border-b border-sky-100 bg-white/85 backdrop-blur-xl">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
          <button onClick={() => setPage("home")} className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-lg bg-sky-500 text-white shadow-lg shadow-sky-200">💼</div>
            <div className="text-left">
              <p className="text-xl font-extrabold text-slate-950">KDXJobs</p>
              <p className="text-xs font-medium text-sky-600">Tech Hiring Platform</p>
            </div>
          </button>

          <nav className="hidden items-center gap-1 lg:flex">
            {nav.map(([id, label]) => (
              <button key={id} onClick={() => setPage(id)} className={`rounded-lg px-4 py-2 text-sm font-semibold transition ${page === id ? "bg-sky-50 text-sky-700" : "text-slate-600 hover:bg-slate-50"}`}>{label}</button>
            ))}
          </nav>

          <div className="hidden items-center gap-3 md:flex">
            {currentUser ? (
              <>
                <span className="text-sm font-semibold text-slate-600">{userLabel}</span>
                <Button variant="outline" onClick={() => { localStorage.removeItem("recruitpro_user"); setCurrentUser(null); setPage("home"); }}>Logout</Button>
              </>
            ) : (
              <>
                <Button onClick={() => setPage("login")} variant="outline">Login</Button>
                <Button onClick={() => setPage("register")}>Register</Button>
              </>
            )}
          </div>

          <button className="rounded-lg bg-sky-50 p-3 text-sky-700 lg:hidden" onClick={() => setOpen(!open)}>{open ? "✕" : "☰"}</button>
        </div>
        {open && (
          <div className="border-t border-sky-100 bg-white px-6 py-4 lg:hidden">
            <div className="grid gap-2">
              {nav.map(([id, label]) => (
                <button key={id} onClick={() => { setPage(id); setOpen(false); }} className="rounded-lg px-4 py-3 text-left font-semibold text-slate-600 hover:bg-sky-50">{label}</button>
              ))}
            </div>
          </div>
        )}
      </header>

      {status.loading && <div className="px-6 py-4 text-center text-sm font-semibold text-sky-700">Loading database data...</div>}
      {status.error && (
        <div className="mx-auto mt-6 max-w-4xl rounded-lg border border-red-100 bg-red-50 px-5 py-4 text-red-700">
          Database connection failed: {status.error}. Open <strong>api/install.php</strong> once, then refresh this page.
        </div>
      )}

      {page === "home" && <HomePage setPage={setPage} data={data} setSelectedJob={setSelectedJob} />}
      {page === "jobs" && <JobsPage data={data} selectedJob={selectedJob} setSelectedJob={setSelectedJob} reload={reload} currentUser={currentUser} />}
      {page === "companies" && <CompaniesPage data={data} />}
      {page === "login" && <AuthPage mode="login" reload={reload} setCurrentUser={setCurrentUser} setPage={setPage} />}
      {page === "register" && <AuthPage mode="register" reload={reload} setCurrentUser={setCurrentUser} setPage={setPage} />}
      {page === "user" && <DashboardShell type="user" data={data} reload={reload} currentUser={currentUser} />}
      {page === "company" && <DashboardShell type="company" data={data} reload={reload} currentUser={currentUser} />}
      {page === "admin" && <DashboardShell type="admin" data={data} reload={reload} currentUser={currentUser} />}

      <footer className="mt-16 border-t border-sky-100 bg-white px-6 py-10">
        <div className="mx-auto flex max-w-7xl flex-col justify-between gap-4 md:flex-row md:items-center">
          <div>
            <p className="font-extrabold text-slate-950">KDXJobs</p>
            <p className="text-sm text-slate-500">Modern recruitment platform for job seekers, companies, and admins.</p>
          </div>
          <p className="text-sm text-slate-500">© 2026 KDXJobs. All rights reserved.</p>
        </div>
      </footer>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<RecruitmentWebsite />);
