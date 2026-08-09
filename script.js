// ------------------------------
// Saved Data
// ------------------------------

// Read an item from localStorage. If it does not exist yet, use the default value.
function loadData(key, defaultValue) {
    var savedData = localStorage.getItem(key);

    if (savedData === null) {
        return defaultValue;
    }

    return JSON.parse(savedData);
}

// These arrays hold the app's data while the page is open.
var subjects = loadData("acadtrackSubjects", []);
var studySessions = loadData("acadtrackStudySessions", []);
var goals = loadData("acadtrackGoals", []);
var completedPomodoros = loadData("acadtrackPomodoros", 0);
var weeklyAssessment = loadData("acadtrackWeeklyAssessment", null);

function saveData() {
    localStorage.setItem("acadtrackSubjects", JSON.stringify(subjects));
    localStorage.setItem("acadtrackStudySessions", JSON.stringify(studySessions));
    localStorage.setItem("acadtrackGoals", JSON.stringify(goals));
    localStorage.setItem("acadtrackPomodoros", JSON.stringify(completedPomodoros));
    localStorage.setItem("acadtrackWeeklyAssessment", JSON.stringify(weeklyAssessment));
}

// ------------------------------
// Navigation
// ------------------------------

var pageTitles = {
    dashboard: "Dashboard",
    subjects: "Subjects & Marks",
    study: "Study Timer",
    goals: "Study Goals"
};

function showSection(sectionId) {
    var sections = document.querySelectorAll(".page-section");
    var navigationButtons = document.querySelectorAll(".nav-link");

    sections.forEach(function (section) {
        section.classList.remove("active-section");
    });

    navigationButtons.forEach(function (button) {
        button.classList.remove("active");
        if (button.dataset.section === sectionId) {
            button.classList.add("active");
        }
    });

    document.getElementById(sectionId).classList.add("active-section");
    document.getElementById("page-title").textContent = pageTitles[sectionId];
    window.location.hash = sectionId;
    window.scrollTo(0, 0);
}

document.querySelectorAll(".nav-link").forEach(function (button) {
    button.addEventListener("click", function () {
        showSection(button.dataset.section);
    });
});

document.querySelectorAll("[data-go-to]").forEach(function (button) {
    button.addEventListener("click", function (event) {
        event.preventDefault();
        showSection(button.dataset.goTo);
    });
});

// This also makes the browser's Back and Forward buttons switch sections.
window.addEventListener("hashchange", function () {
    var sectionFromHash = window.location.hash.replace("#", "");
    if (pageTitles[sectionFromHash]) {
        showSection(sectionFromHash);
    }
});

// ------------------------------
// Grade Calculation
// ------------------------------

function calculatePercentage(obtainedMarks, totalMarks) {
    return (obtainedMarks / totalMarks) * 100;
}

// The first true condition decides the grade.
function calculateGrade(percentage) {
    if (percentage >= 80) return "A";
    if (percentage >= 75) return "B+";
    if (percentage >= 70) return "B";
    if (percentage >= 60) return "C";
    return "F";
}

// ------------------------------
// Subject Functions
// ------------------------------

var subjectForm = document.getElementById("subject-form");

subjectForm.addEventListener("submit", function (event) {
    event.preventDefault();

    var name = document.getElementById("subject-name").value.trim();
    var total = Number(document.getElementById("total-marks").value);
    var obtained = Number(document.getElementById("obtained-marks").value);
    var error = document.getElementById("subject-error");

    if (obtained > total) {
        error.textContent = "Obtained marks cannot be greater than total marks.";
        return;
    }

    error.textContent = "";

    var newSubject = {
        id: Date.now(),
        name: name,
        totalMarks: total,
        obtainedMarks: obtained,
        createdAt: new Date().toISOString()
    };

    subjects.push(newSubject);
    saveData();
    subjectForm.reset();
    updateAllDisplays();
});

function deleteSubject(subjectId) {
    subjects = subjects.filter(function (subject) {
        return subject.id !== subjectId;
    });

    // Remove goals for a subject that no longer exists.
    goals = goals.filter(function (goal) {
        return goal.subjectId !== subjectId;
    });

    saveData();
    updateAllDisplays();
}

function displaySubjects() {
    var tableBody = document.getElementById("subject-table-body");
    var emptyState = document.getElementById("subject-empty-state");
    tableBody.innerHTML = "";

    if (subjects.length === 0) {
        emptyState.style.display = "block";
        return;
    }

    emptyState.style.display = "none";

    subjects.forEach(function (subject) {
        var percentage = calculatePercentage(subject.obtainedMarks, subject.totalMarks);
        var row = document.createElement("tr");

        row.innerHTML =
            "<td class='subject-name-cell'></td>" +
            "<td>" + subject.obtainedMarks + "/" + subject.totalMarks + "</td>" +
            "<td>" + percentage.toFixed(1) + "%</td>" +
            "<td><span class='grade-badge'>" + calculateGrade(percentage) + "</span></td>" +
            "<td><button class='delete-button' aria-label='Delete subject'>Delete</button></td>";

        // textContent safely displays a name typed by the user.
        row.querySelector(".subject-name-cell").textContent = subject.name;
        row.querySelector(".delete-button").addEventListener("click", function () {
            deleteSubject(subject.id);
        });

        tableBody.appendChild(row);
    });
}

function updateSubjectDropdowns() {
    var studyDropdown = document.getElementById("study-subject");
    var goalDropdown = document.getElementById("goal-subject");

    studyDropdown.innerHTML = '<option value="">Choose a subject</option>';
    goalDropdown.innerHTML = '<option value="">Choose a subject</option>';

    subjects.forEach(function (subject) {
        var studyOption = document.createElement("option");
        studyOption.value = subject.id;
        studyOption.textContent = subject.name;
        studyDropdown.appendChild(studyOption);

        var goalOption = document.createElement("option");
        goalOption.value = subject.id;
        goalOption.textContent = subject.name;
        goalDropdown.appendChild(goalOption);
    });
}

// ------------------------------
// Required Marks Calculator
// ------------------------------

document.getElementById("marks-calculator").addEventListener("submit", function (event) {
    event.preventDefault();

    var currentMarks = Number(document.getElementById("current-marks").value);
    var totalMarks = Number(document.getElementById("calculator-total").value);
    var targetPercentage = Number(document.getElementById("target-percentage").value);
    var result = document.getElementById("calculator-result");

    if (currentMarks > totalMarks) {
        result.textContent = "Current marks cannot be greater than total marks.";
        return;
    }

    // Math.ceil rounds up because a student cannot earn part of a whole mark.
    var requiredTotal = Math.ceil((targetPercentage / 100) * totalMarks);
    var extraMarks = requiredTotal - currentMarks;

    if (extraMarks <= 0) {
        result.textContent = "Target reached! You already have enough marks for " + targetPercentage + "%.";
    } else {
        result.textContent = "You need at least " + requiredTotal + "/" + totalMarks +
            " marks in total. That is " + extraMarks + " more mark" + (extraMarks === 1 ? "." : "s.");
    }
});

// ------------------------------
// Pomodoro Timer
// ------------------------------

var defaultTimerSeconds = 25 * 60;
var remainingSeconds = defaultTimerSeconds;
var timerInterval = null;

function displayTimer() {
    var minutes = Math.floor(remainingSeconds / 60);
    var seconds = remainingSeconds % 60;
    var formattedSeconds = seconds < 10 ? "0" + seconds : seconds;
    document.getElementById("timer-display").textContent = minutes + ":" + formattedSeconds;
}

document.getElementById("start-timer").addEventListener("click", function () {
    // Do not create another interval if the timer is already running.
    if (timerInterval !== null) return;

    timerInterval = setInterval(function () {
        remainingSeconds = remainingSeconds - 1;
        displayTimer();

        if (remainingSeconds <= 0) {
            clearInterval(timerInterval);
            timerInterval = null;
            completedPomodoros = completedPomodoros + 1;
            remainingSeconds = defaultTimerSeconds;
            saveData();
            updateDashboard();
            displayTimer();
            alert("Pomodoro complete! Take a short break.");
        }
    }, 1000);
});

document.getElementById("pause-timer").addEventListener("click", function () {
    clearInterval(timerInterval);
    timerInterval = null;
});

document.getElementById("reset-timer").addEventListener("click", function () {
    clearInterval(timerInterval);
    timerInterval = null;
    remainingSeconds = defaultTimerSeconds;
    displayTimer();
});

// ------------------------------
// Study Tracker
// ------------------------------

document.getElementById("study-form").addEventListener("submit", function (event) {
    event.preventDefault();

    var subjectId = Number(document.getElementById("study-subject").value);
    var duration = Number(document.getElementById("study-duration").value);
    var error = document.getElementById("study-error");
    var selectedSubject = subjects.find(function (subject) {
        return subject.id === subjectId;
    });

    if (!selectedSubject) {
        error.textContent = "Add and choose a subject first.";
        return;
    }

    error.textContent = "";
    studySessions.push({
        id: Date.now(),
        subjectId: subjectId,
        subjectName: selectedSubject.name,
        duration: duration,
        date: new Date().toISOString()
    });

    saveData();
    document.getElementById("study-form").reset();
    updateAllDisplays();
});

function isToday(dateText) {
    var savedDate = new Date(dateText);
    var today = new Date();

    return savedDate.getFullYear() === today.getFullYear() &&
        savedDate.getMonth() === today.getMonth() &&
        savedDate.getDate() === today.getDate();
}

function displayTodaySessions() {
    var container = document.getElementById("today-sessions");
    var todaysSessions = studySessions.filter(function (session) {
        return isToday(session.date);
    });

    container.innerHTML = "";

    if (todaysSessions.length === 0) {
        container.innerHTML = '<div class="empty-state"><span>◷</span><h3>No study time logged today</h3><p>Your sessions will appear here.</p></div>';
        return;
    }

    todaysSessions.slice().reverse().forEach(function (session) {
        var item = document.createElement("div");
        item.className = "session-item";

        var details = document.createElement("div");
        details.className = "session-details";
        details.innerHTML = '<span class="activity-dot"></span><div><p></p><small></small></div>';
        details.querySelector("p").textContent = session.subjectName;
        details.querySelector("small").textContent = new Date(session.date).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });

        var duration = document.createElement("span");
        duration.className = "session-duration";
        duration.textContent = session.duration + " minutes";

        item.appendChild(details);
        item.appendChild(duration);
        container.appendChild(item);
    });
}

// ------------------------------
// Goals
// ------------------------------

document.getElementById("goal-form").addEventListener("submit", function (event) {
    event.preventDefault();

    var subjectId = Number(document.getElementById("goal-subject").value);
    var target = Number(document.getElementById("goal-target").value);
    var error = document.getElementById("goal-error");
    var selectedSubject = subjects.find(function (subject) {
        return subject.id === subjectId;
    });

    if (!selectedSubject) {
        error.textContent = "Add and choose a subject first.";
        return;
    }

    var existingGoal = goals.find(function (goal) {
        return goal.subjectId === subjectId;
    });

    if (existingGoal) {
        existingGoal.target = target;
    } else {
        goals.push({ id: Date.now(), subjectId: subjectId, target: target });
    }

    error.textContent = "";
    saveData();
    document.getElementById("goal-form").reset();
    displayGoals();
});

function deleteGoal(goalId) {
    goals = goals.filter(function (goal) {
        return goal.id !== goalId;
    });
    saveData();
    displayGoals();
}

function displayGoals() {
    var container = document.getElementById("goal-list");
    container.innerHTML = "";

    if (goals.length === 0) {
        container.innerHTML = '<div class="empty-state"><span>◎</span><h3>No goals set</h3><p>Choose a subject and add a target percentage.</p></div>';
        return;
    }

    goals.forEach(function (goal) {
        var subject = subjects.find(function (item) {
            return item.id === goal.subjectId;
        });

        if (!subject) return;

        var current = calculatePercentage(subject.obtainedMarks, subject.totalMarks);
        var progress = Math.min((current / goal.target) * 100, 100);
        var card = document.createElement("div");
        card.className = "goal-card";
        card.innerHTML =
            '<div class="goal-topline"><h3></h3><button class="delete-button">Remove</button></div>' +
            '<div class="goal-numbers"><span>Current: <strong>' + current.toFixed(1) + '%</strong></span>' +
            '<span>Target: <strong>' + goal.target + '%</strong></span>' +
            '<span class="goal-progress-text">' + progress.toFixed(0) + '%</span></div>' +
            '<div class="bar-track"><div class="bar-fill" style="width: ' + progress + '%"></div></div>';

        card.querySelector("h3").textContent = subject.name;
        card.querySelector(".delete-button").addEventListener("click", function () {
            deleteGoal(goal.id);
        });
        container.appendChild(card);
    });
}

// ------------------------------
// Weekly Self-Assessment
// ------------------------------

document.getElementById("weekly-checkin-form").addEventListener("submit", function (event) {
    event.preventDefault();

    var studyHours = Number(document.getElementById("weekly-study-hours").value);
    var attendance = Number(document.getElementById("weekly-attendance").value);
    var performance = Number(document.getElementById("class-performance").value);
    var error = document.getElementById("checkin-error");

    if (studyHours < 0 || studyHours > 168) {
        error.textContent = "Study hours must be between 0 and 168.";
        return;
    }

    if (attendance < 0 || attendance > 100) {
        error.textContent = "Attendance must be between 0% and 100%.";
        return;
    }

    if (performance < 1 || performance > 10) {
        error.textContent = "Class performance must be rated from 1 to 10.";
        return;
    }

    // Class performance is a self-rating based on the student's full classroom effort.
    // It includes assignments, lab reports, classwork, preparation, participation, and focus.
    weeklyAssessment = {
        studyHours: studyHours,
        attendance: attendance,
        performance: performance,
        savedAt: new Date().toISOString()
    };

    error.textContent = "";
    saveData();
    displayWeeklyAssessment();
});

function displayWeeklyAssessment() {
    var container = document.getElementById("weekly-checkin-summary");

    if (weeklyAssessment === null) {
        container.innerHTML = '<div class="empty-state"><span>◷</span><h3>No weekly check-in yet</h3><p>Complete the form to create your first summary.</p></div>';
        return;
    }

    var savedDate = new Date(weeklyAssessment.savedAt).toLocaleDateString([], {
        month: "long",
        day: "numeric",
        year: "numeric"
    });

    container.innerHTML =
        '<p class="checkin-date">Saved on ' + savedDate + '</p>' +
        '<div class="checkin-metrics">' +
            '<div class="checkin-metric"><span>Weekly study</span><strong>' + weeklyAssessment.studyHours + ' hours</strong></div>' +
            '<div class="checkin-metric"><span>Attendance</span><strong>' + weeklyAssessment.attendance + '%</strong></div>' +
            '<div class="checkin-metric"><span>Class performance</span><strong>' + weeklyAssessment.performance + '/10</strong></div>' +
        '</div>' +
        '<p class="performance-note">The performance rating reflects your overall classroom effort: assignments, lab reports, classwork, preparation, participation, and focus.</p>';
}

// ------------------------------
// Dashboard Update
// ------------------------------

function updateDashboard() {
    var totalPercentage = 0;
    var totalMinutes = 0;

    subjects.forEach(function (subject) {
        totalPercentage += calculatePercentage(subject.obtainedMarks, subject.totalMarks);
    });

    studySessions.forEach(function (session) {
        totalMinutes += session.duration;
    });

    var average = subjects.length > 0 ? totalPercentage / subjects.length : 0;
    var studyHours = totalMinutes / 60;

    document.getElementById("total-subjects").textContent = subjects.length;
    document.getElementById("average-score").textContent = average.toFixed(1) + "%";
    document.getElementById("total-study-time").textContent = studyHours.toFixed(1) + "h";
    document.getElementById("completed-sessions").textContent = completedPomodoros;
    document.getElementById("timer-session-count").textContent = completedPomodoros;
}

function displayPerformanceChart() {
    var chart = document.getElementById("performance-chart");
    chart.innerHTML = "";

    if (subjects.length === 0) {
        chart.innerHTML = '<div class="empty-state"><span>▥</span><h3>No performance data</h3><p>Add subject marks to create your chart.</p></div>';
        return;
    }

    subjects.forEach(function (subject) {
        var percentage = calculatePercentage(subject.obtainedMarks, subject.totalMarks);
        var bar = document.createElement("div");
        bar.innerHTML =
            '<div class="bar-label"><span></span><span>' + percentage.toFixed(1) + '% · ' + calculateGrade(percentage) + '</span></div>' +
            '<div class="bar-track"><div class="bar-fill" style="width: ' + percentage + '%"></div></div>';
        bar.querySelector(".bar-label span").textContent = subject.name;
        chart.appendChild(bar);
    });
}

function displayRecentActivity() {
    var container = document.getElementById("recent-activity");
    var activities = [];

    subjects.forEach(function (subject) {
        activities.push({ text: "Added " + subject.name, date: subject.createdAt });
    });

    studySessions.forEach(function (session) {
        activities.push({ text: "Studied " + session.subjectName + " for " + session.duration + " min", date: session.date });
    });

    activities.sort(function (first, second) {
        return new Date(second.date) - new Date(first.date);
    });

    container.innerHTML = "";

    if (activities.length === 0) {
        container.innerHTML = '<div class="empty-state"><span>○</span><h3>Nothing here yet</h3><p>Your latest updates will appear here.</p></div>';
        return;
    }

    activities.slice(0, 5).forEach(function (activity) {
        var item = document.createElement("div");
        item.className = "activity-item";
        item.innerHTML = '<span class="activity-dot"></span><div><p></p><small></small></div>';
        item.querySelector("p").textContent = activity.text;
        item.querySelector("small").textContent = new Date(activity.date).toLocaleDateString([], { month: "short", day: "numeric" });
        container.appendChild(item);
    });
}

function updateAllDisplays() {
    displaySubjects();
    updateSubjectDropdowns();
    displayTodaySessions();
    displayGoals();
    updateDashboard();
    displayPerformanceChart();
    displayRecentActivity();
    displayWeeklyAssessment();
}

// ------------------------------
// Start the App
// ------------------------------

document.getElementById("current-date").textContent = new Date().toLocaleDateString([], {
    weekday: "long",
    month: "long",
    day: "numeric"
});

var startingSection = window.location.hash.replace("#", "");
if (pageTitles[startingSection]) {
    showSection(startingSection);
}

displayTimer();
updateAllDisplays();
