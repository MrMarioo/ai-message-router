<?php

namespace App\Enums;

enum Department: string
{
    case HumanResources = 'human-resources@example.com';
    case HelpDesk = 'help-desk@example.com';
    case It = 'it@example.com';
    case Kadry = 'kadry@example.com';
    case Other = 'other@example.com';

    public static function fallback(): self
    {
        return self::Other;
    }

    public static function tryFromAddress(?string $address): ?self
    {
        return $address === null ? null : self::tryFrom(strtolower(trim($address)));
    }

    /**
     * @return array<int, string>
     */
    public static function addresses(): array
    {
        return array_map(fn (self $department) => $department->value, self::cases());
    }

    public function description(): string
    {
        return match ($this) {
            self::Kadry => 'Formal personnel and payroll administration: vacation / leave requests (urlop), sick leave (L4, zwolnienie lekarskie), employment contracts, salary and payslips, employment certificates (zaświadczenia), working time records.',
            self::HumanResources => 'Recruitment, job applications, onboarding of new employees, trainings, benefits, performance reviews, complaints about colleagues or managers, workplace conflicts. Not responsible for computers, emails or security.',
            self::HelpDesk => 'First-line user support: a computer, laptop, printer or phone does not work, password reset, locked account, missing access to an application, software installation, email or Office problems, "how do I" questions.',
            self::It => 'Infrastructure and security: network or Wi-Fi outages, VPN, servers, databases, ALL security incidents (phishing or suspicious emails, malware, suspicious logins, data leaks), ordering new hardware, system permissions, deployments and developer tooling.',
            self::Other => 'Anything that does not clearly belong to the departments above, or is too vague to classify (e.g. general questions, facilities, parking, catering, spam).',
        };
    }
}
