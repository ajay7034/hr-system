import { useEffect, useMemo, useRef, useState } from 'react';

export default function EmployeeAutocomplete({
  employees = [],
  value,
  onChange,
  label = 'Employee',
  placeholder = 'Search employee name or code',
  required = false,
  disabled = false,
}) {
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const rootRef = useRef(null);
  const inputRef = useRef(null);

  const selectedEmployee = useMemo(
    () => employees.find((employee) => String(employee.id) === String(value)),
    [employees, value]
  );

  useEffect(() => {
    setQuery(selectedEmployee ? `${selectedEmployee.full_name} (${selectedEmployee.employee_code})` : '');
  }, [selectedEmployee]);

  useEffect(() => {
    if (!inputRef.current) {
      return;
    }

    if (required && !value) {
      inputRef.current.setCustomValidity('Select an employee from the list');
      return;
    }

    inputRef.current.setCustomValidity('');
  }, [required, value]);

  useEffect(() => {
    function handleOutsideClick(event) {
      if (!rootRef.current?.contains(event.target)) {
        setOpen(false);
      }
    }

    document.addEventListener('mousedown', handleOutsideClick);
    return () => document.removeEventListener('mousedown', handleOutsideClick);
  }, []);

  const filteredEmployees = useMemo(() => {
    const term = query.trim().toLowerCase();

    if (!term) {
      return employees.slice(0, 8);
    }

    return employees
      .filter((employee) => {
        const haystack = [
          employee.full_name,
          employee.employee_code,
          employee.employee_id,
          employee.email,
          employee.passport_number,
        ]
          .filter(Boolean)
          .join(' ')
          .toLowerCase();

        return haystack.includes(term);
      })
      .slice(0, 8);
  }, [employees, query]);

  return (
    <label className="field">
      <span>{label}</span>
      <div className={`autocomplete ${disabled ? 'is-disabled' : ''}`} ref={rootRef}>
        <input
          ref={inputRef}
          value={query}
          placeholder={placeholder}
          required={required}
          disabled={disabled}
          onFocus={() => setOpen(true)}
          onBlur={() => {
            window.setTimeout(() => setOpen(false), 120);
          }}
          onChange={(event) => {
            setQuery(event.target.value);
            setOpen(true);
            onChange('');
          }}
        />
        <input type="hidden" value={value || ''} />
        {open && !disabled ? (
          <div className="autocomplete-menu">
            {filteredEmployees.length ? (
              filteredEmployees.map((employee) => (
                <button
                  type="button"
                  key={employee.id}
                  className={`autocomplete-option ${String(employee.id) === String(value) ? 'selected' : ''}`}
                  onClick={() => {
                    onChange(String(employee.id));
                    setQuery(`${employee.full_name} (${employee.employee_code})`);
                    setOpen(false);
                  }}
                >
                  <strong>{employee.full_name}</strong>
                  <span>
                    {employee.employee_code}
                    {employee.email ? ` • ${employee.email}` : ''}
                  </span>
                </button>
              ))
            ) : (
              <div className="autocomplete-empty">No employee found</div>
            )}
          </div>
        ) : null}
      </div>
    </label>
  );
}
